<?php
/**
 * uploader_back.php
 * Erick Veil
 * 2013-07-02
 *
 * Uploading a file is more of a hassle to develop than it should be. It
 * takes coordination between the front-end scripts and PHP,
 * the browser and the server, and the configuration of PHP. You select a
 * file, press submit, you have to deal with the page reloading,
 * keep the user following a linear, step-by-step process.
 *
 * It's a hassle.
 *
 * Now, you can just instantiate this Uploader class in the spot in your PHP
 * script where you want the complete form to be drawn. The constructor
 * handles including the javascript front-end, drawing the multiple form
 * components, making them work together, preserving our other POST variable
 * through the obnoxious page reload submission process,
 * and validating the upload.
 *
 * Define your own additional back-end validation routines and pass a
 * function pointer to them in the Uploader constructor. Define your own
 * JavaScript and ajax post-upload file processing routine and pass a
 * function pointer to the JavaScript function in the constructor.
 *
 * Or not. Pass blank strings for the functions and skip that step.
 *
 * The only other work you need to do is make sure your php.ini is configured
 * for uploading.
 *
 * This file should be included in your PHP script where you want to
 * instantiate the Uploader.
 *
 * This file also holds functions for the front
 * end's ajax calls, defined after the class.
 *
 * Requires jQuery.
 *
 * Multiple upload forms per page untested, and probably broken.
 *
 */

/**
 * Class Uploader
 * Easy upload file object for PHP.
 *
 * Instantiate this class at the spot in your PHP application where you want
 * to draw the form. Draws the file element, the submit,
 * and cancel buttons. Handles the state of the upload during the submission
 * process. See constructor docs for member value significance.
 */
class Uploader
{
    public $ul_dir;
    public $tmp_dir;
    public $id;
    public $validator;
    public $js_post_fn;

    /**
     * @param $ul_id string unique identifier
     * This identifies the uploader as unique, in a page with multiple
     *
     * @param $upload_dir string directory path
     * This is where the file will be after validation
     *
     * @param $temp_dir string directory path
     * This is where ph is configured to upload the file to initially
     *
     * @param $valid_fn string function($_FILES)
     * A function name of a custom validation function that the dev can use
     * to add additional validation parameters. Must return true or false and
     * must accept the FILES superglobal as a parameter.
     * If left as an empty string, Defaults to skip any additional validation.
     *
     * @param $js string JavaScript function name
     * Holds the name of a JAVASCRIPT function. This function will be called
     * at the conclusion of a successful upload. If this parameter is an
     * empty string, a default function from uploader_front.js will be called
     * that does nothing, resulting in the file merely being uploaded to the
     * defined location. This script will be called with no parameters.
     */
    function __construct($ul_id, $upload_dir, $temp_dir, $valid_fn, $js)
    {
        $this->ul_dir=$upload_dir;
        $this->tmp_dir=$temp_dir;
        $this->id=$ul_id;

        if($valid_fn=="")
        {
            $this->validator="passValidation";
        }
        else
        {
            $this->validator=$valid_fn;
        }

        if($js=="")
        {
            $this->js_post_fn="defaultPost";
        }
        else
        {
            $this->js_post_fn=$js;
        }

        echo "<script type='text/javascript' src='uploader_front.js'></script>\n\n";

        echo "
            <input
                type='hidden'
                id='post_upload_js'
                value='".$this->js_post_fn."'
            />
        ";

        echo $this->htmlDOMData();

        // if you make an ajax call to re-draw the form,
        // hang your html from this hook.
        echo "<div id='ul_hook' >";
        echo htmlUploadForm($this->id);
        echo "</div>";

        $this->uploadEvent();
    }

    /**
     * 0.1.0
     * Called from the constructor.
     * Runs on every page load that the Uploader is instantiated on.
     * Handles keeping track of what step in the upload process we are at.
     * If the FILES superglobal is set, then a file has been selected,
     * and the upload submission button had been pushed,
     * resulting in this page load.
     * In this case, we call the validation, and draw to the page a hidden
     * DOM object with parameters that describe the state of the uploaded
     * file. These parameters are read by jQuery after the DOM loads,
     * and the front end responds by altering the upload form to reflect the
     * new state.
     */
    private function uploadEvent()
    {
        if(isset($_FILES[$this->id]))
        {
            $val_str=$this->validateUpload($_FILES,$_POST);
            $fname=$_FILES[$this->id]['name'];
            //echo $val_str;

            // js on dom load will detect these and act accordingly
            if($val_str=="")
            {
                echo"
                    <input type='hidden'
                        id='upload_done'
                        value='success'
                        filename='".$this->ul_dir."${fname}'
                    />
                ";
            }
            else
            {
                echo"
                    <input type='hidden'
                        id='upload_done'
                        value='failed'
                        filename='".$this->ul_dir."${fname}'
                    />
                ";
                $this->cleanUploadDir();
            }

        }
    }

    /**
     * 0.1.1
     * performs basic validations on an uploaded file, just to be sure the file
     * actually got uploaded
     *
     * @param $file_obj
     * @param $post_obj
     * @return string
     */
    private function validateUpload($file_obj,$post_obj)
    {
        $handle=$this->id;

        $filename=$file_obj[$handle]['name'];
        $tmpname=$file_obj[$handle]['tmp_name'];
        $filetype=$file_obj[$handle]['type'];
        $file_size=$file_obj[$handle]['size'];
        $error_code=$file_obj[$handle]['error'];
        $max_size=$post_obj['MAX_FILE_SIZE'];

        $err=$this->fileError($error_code);
        if($err!="")
            return $err;

        $uploadfile=$this->ul_dir.basename($filename);
        if(move_uploaded_file($tmpname,$uploadfile)===false)
        {
            $this->cleanUploadDir();
            return "File upload failed: Failed to move file into update directory.";
        }

        if($this->validateFilesize($file_size,$max_size)===false)
        {
            $this->cleanUploadDir();
            return "File upload failed: File too big.";
        }

        $fn=$this->validator;
        if($fn($file_obj)===false)
        {
            $this->cleanUploadDir();
            return "File upload failed: Not a valid file.";
        }

        // validations successful
        return "";
    }

    /**
     * 0.1.1.1
     * translates the received error codes from a faulty file upload
     * returns a string explaining the error, or "" if no error.
     *
     * @param $error_code
     * @return string
     */
    private function fileError($error_code)
    {
        $err_msg="Error: ";
        switch($error_code)
        {
            case UPLOAD_ERR_OK:
                return "";
            break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $err_msg.="The file is too large.";
            break;
            case UPLOAD_ERR_PARTIAL:
                $err_msg.="The file was only partially uploaded.";
            break;
            case UPLOAD_ERR_NO_FILE:
                $err_msg.="No file was uploaded.";
            break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $err_msg.="Temp upload directory not defined.";
            break;
            case UPLOAD_ERR_CANT_WRITE:
                $err_msg.="Failed to write file to disk.";
            break;
            case UPLOAD_ERR_EXTENSION:
                $err_msg.="File upload stopped by extension.";
            break;
            default:
                $err_msg.="File upload failed. Unknown reason.";
            break;
        }
        return $err_msg;
    }

    /**
     * 0.1.1.2
     * files must be within the file size parameters
     * @param $size
     * @param $max
     * @return bool
     */
    private function validateFilesize($size,$max)
    {
        return ($size<=$max);
    }

    /**
     * 0.1.2
     *
     * clears out all files from the update directory
     */
    public function cleanUploadDir()
    {
        if(!isset($_FILES[$this->id]))
            return;

        $fname=$_FILES[$this->id]['name'];

        $files=array();
        $files[]=$this->ul_dir.$fname;

        foreach($files as $file)
        {
            if(file_exists($file))
            {
                unlink($file);
            }
        }
    }

    /**
     * 0.3.0
     * The physical representation of the instantiated object on the page.
     * The public class data is made available as DOM attributes for access
     * by JavaScript or other script languages.
     *
     * Eg:
     * To access this class's JavaScript function name in JQuery:
     * $("#uploader_data").attr("js_post");
     *
     * @return string
     */
    function htmlDOMData()
    {
        $html="";
        $html.="<input";
        $html.="    type='hidden'";
        $html.="    id='uploader_data'";
        $html.="    ul_dir='".$this->ul_dir."'";
        $html.="    tmp_dir='".$this->tmp_dir."'";
        $html.="    ul_id='".$this->id."'";
        $html.="    validator='".$this->validator."'";
        $html.="    js_post='".$this->js_post_fn."'";
        $html.="/>";
        return $html;
    }

    // end of class
}

/**
 * 0.0.0
 * Constructs and returns the html that draws the actual elements that
 * control the upload form. Note that this is called and output in the
 * constructor, so the form will be drawn at the point in the app
 * where we instantiate the Uploader.
 *
 * @param id
 * @return string
 */
function htmlUploadForm($id)
{
    $html="";
    $html.="<span id='upload_form' >";
    $html.="<form enctype='multipart/form-data' method='POST' >";

    $html.="<input type='hidden' name='MAX_FILE_SIZE' value='50000000000' />";
    $html=gatherPostValues($html, $id);

    $html.="<input name='".$id."' type='file' />";
    $html.="<input type='submit' value='Upload' />";

    $html.="</form>";
    $html.="</span>";
    $html.="<input type='button' id='ul_cancel' value='Cancel' />";

    return $html;
}

/**
 * 0.0.1
 * In a perfect world, an upload would not require to submit/reload a
 * page, just like any other button or check-box doesn't. Ajax calls
 * *can* perform this, but Internet Explorer and its users are holding the
 * internet back.
 * For now, I have to refresh the page at every state change to
 * accommodate primitive browsers. In order to preserve any important POST
 * values generated by other processes, we gather them in hidden
 * elements, and re-post them as we submit for the upload.
 *
 * In about 30 years, we can probably say goodbye to IE < 10.
 *
 * @param $html
 * @param $id
 * @return string
 */
function gatherPostValues($html, $id)
{
    foreach($_POST as $key=>$value)
    {
        $html.="".
        "<input".
        "    class='p_${id}'".
        "    type='hidden' name='${key}'".
        "    value='${value}'".
        "/>";
    }
    return $html;
}

/**
 * 0.2.0
 * Default value for custom validation function. Always passes. Parameter
 * is just for format validity.
 *
 * @param $file_obj
 * @return bool
 */
function passValidation($file_obj)
{
    return true;
}

//====================================================================
// Here thar be ajax responses.
//====================================================================


/**
 * Note that this function gets called every time the app script is run,
 * meaning every time the page is loaded. Most time,
 * it will pass through with no action taken, unless the page is loaded with
 * ?ul_fun=<defined_case> on the URI.
 *
 * The intention is that this function is only applicable during an ajax call
 * to this script. The app itself should not contain the ul_fun value in its
 * URI.
 *
 * Only use the ul_fun URI component if calling this script specifically from
 * an ajax call.
 *
 * @param $get array: the GET variable
 */
function uploadAjaxRequest($get)
{
    if(!isset($get['ul_fun']))
        return;

    $fun=$get['ul_fun'];
    switch($fun)
    {
        /**
         * When a cancel button is pressed, delete the uploaded file.
         */
        case "cancelUpload":
            if(isset($get['file']))
            {
                unlink($get['file']);
            }
        break;
        default:
            echo "fail";
        break;
    }
}

uploadAjaxRequest($_GET);

