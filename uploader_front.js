/**
 * Requires jQuery
 *
 * Requires uploader_back.php
 *
 * See uploader_back.php for instructions.
 *
 */

/**
 * DOM on load
 * Reacts to the upload state. The upload state is controlled by
 * uploader_back.php.
 * The back draws a hidden form element to the page, which this function
 * detects and uses to determine what step of uploading we are on.
 *
 * The states are either:
 * pre-upload
 * post-submission success
 * post submission failure
 */
$(function(){

    // the done_ele only appears drawn on the page after an upload has been
    // submitted, and the page has been re-drawn from the submit process.
    var done_ele=$("#upload_done");
    var num_completions=$(done_ele).length;

    /*
     * If num_completions exists, then an upload has occurred, and we proceed to
     * the next step. If the element value is 'success' the upload has passed
     * all server-side validations. In this case, proceed with app determined
     * javascript call. If the value comes up failure, the uploaded file
     * should be eliminated, and the process is treated as a cancel, after
     * notifying th user that their attempt failed.
     */
    if(num_completions>0)
    {
        completeUpload();
    }

    setCancelClick();


});

function setCancelClick()
{
    $("#ul_cancel").click(function(){
        cancelUpload();
        //resetUploadForm();
    });
}

/**
 * Called post-upload.
 * If the upload succeeded, run the post-upload script.
 * If failed, cancel the upload.
 * Changes the upload form contents to reflect the current state.
 */
function completeUpload()
{
    var done_ele=$("#upload_done");

    if($(done_ele).attr("value")=="success")
    {
        // must be defined for app page
        execPostUploadScript();
        //resetUploadForm();
    }
    else
    {
        var fail_contents="";
        // The form must be preserved because it contains the POST values,
        // which would otherwise be lost.
        fail_contents+=$("#upload_form").html();
        fail_contents+="<H2>File Upload Failed</H2>";
        fail_contents+="<p>Make sure you are uploading the correct file.</p>";

        $("#upload_form").html(fail_contents);
    }
}

/**
 * Called when the cancel button is pressed.
 * Ajax call to the backend script to delete the uploaded file, if any.
 */
function cancelUpload()
{
    var fname=$("#upload_done").attr("filename");

    if(!fname)
        return;

    $.ajax({
        url:"uploader_back.php?ul_fun=cancelUpload&file="+fname,
        cache:false
    }).done(function(ret_val){
        });
}

/**
 * Called post upload, success.
 * Calls the defined script for any post-upload file processing.
 *
 * eg: Maybe you want to inset that CSV file you uploaded into the MySQL
 * database automatically at this point.
 *
 * Assume the file is valid at this point.
 *
 * Caution, be sure you do not replace the contents of the form. It contains the
 * POST in hidden inputs, which can be lost. Adding the hidden input back in
 * via ajax call fails to preserve them, because the new elements are
 * inaccessible via selector.
 */
function execPostUploadScript()
{
    var script=$("#post_upload_js").attr("value");
    window[script]();
}

/**
 * Default post-upload success function.
 * If no function was passed to the PHP Uploader object, then this is the
 * function that gets called.
 *
 * Default do nothing with the file.
 *
 * @returns {boolean}
 */
function defaultPost()
{
    return true;
}

