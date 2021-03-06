<?php

require_once 'template_filler.php';

function ResourcesDownload( SEEDAppConsole $oApp, $dir_name )
/************************************************************
    Show the documents from the given directory, and if one is clicked download it through the template_filler
 */
{
    $s = "";

    $s .= <<<ResourcesTagStyle
        <style>
            /* Every resources tag and control
             */
            .resources-tag {
                    display:inline-block;
                    font-size:9pt; background-color:#def; margin:0px 2px; padding:0px 3px;
                    border:1px solid #aaa; border-radius:2px;
                  }
            /* [+] new tag button
             */
            .resources-tag-new {
                  }
            /* New tag input control and containing form
             */
            .resources-tag-new-form {
                     display:inline-block;
                  }
            .resources-tag-new-input {
                  }
            #break {
                display: flex;
                justify-content: flex-end;
                min-height: 100px;
                height: auto;
            }
            #ResourceMode {
                box-sizing: border-box;
                display: inline-flex;
                flex-direction: column;
                padding: 7px 10px;
                border: 2px outset #ccc;
                border-style: inset outset outset inset;
                border-radius: 5px;
                justify-content: space-between;
                flex-wrap: wrap;
                background-color: #ccc;
                flex-basis: 20%;
                align-content: space-between;
            }
            #modeText {
                display: flex;
                height: 30px;
                align-items: center;
                padding: 5px;
                margin-bottom: 5px;
            }
            #mode1 {
                margin-bottom: 5px;
            }
            #mode2 {
            }

        </style>
ResourcesTagStyle;

    $s .= <<<ResourcesTagScript
        <script>
        $(document).ready(function() {
            $('.resources-tag-new').click( function() {
                /* The [+] new-tag button opens an input control where the user can type a new tag
                 */
                var tagNew = $("<form class='resources-tag-new-form'>"
                              +"<input class='resources-tag-new-input resources-tag' type='text' value='' placeholder='New tag'/>"
                              +"</form>" );

                /* Put the new-tag form after the [+] button and put focus on its input.
                 * Apparently after() returns the unmodified jQuery i.e. $(this) so we have to use parent().
                 */
                $(this).after( tagNew );
                $(this).parent().find('.resources-tag-new-input').focus();
                /* When the user types something and hits Enter, send their text to the server and draw the new tag
                 * (it will be drawn by the server when the page is refreshed).
                 */
                $(this).parent().find('.resources-tag-new-form').submit(
                    function(e) {
                        e.preventDefault();
                        var tag = $(this).find('input').val();
                        var folder = $(this).parent().data('folder');
                        var filename = $(this).parent().data('filename');
                        SEEDJXAsync( "jx.php", {cmd:"resourcestag--newtag",folder:folder,filename:filename,tag:tag}, function(){}, function(){} );
/* Todo: this puts the tag in place, and it should look the same when the server draws it on the next page refresh.
         But, this is putting the tag into the <form> which isn't there after the page refresh so the spacing is just a little off.
         Replace the <form> with the div.resources-tag, not just its innerhtml.
*/
                        $(this).html("<div class='resources-tag'>"+tag+"</div>");
                    });
            });
        });
        </script>
ResourcesTagScript;

    $resourceMode = <<<DownloadMode
        <div id='break'>
        <div id='ResourceMode'>
            <div id='modeText'><div data-tooltip='[tooltip]'><nobr>Current Mode:</nobr> [mode]</div></div>
            <a id='mode1' href='?resource-mode=%s'><button>%s Mode</button></a>
            <a id='mode2' href='?resource-mode=%s'><button>%s Mode</button></a>
        </div>
        </div>
DownloadMode;

    $mode = $oApp->sess->SmartGPC("resource-mode");
    switch ($mode){
        case 'replace':
            $tooltip = "Program replaces tags with data";
            $resourceMode = str_replace("[mode]", "Substitution", $resourceMode);
            $resourceMode = str_replace("[tooltip]", $tooltip, $resourceMode);
            break;
        case 'no_replace':
            $tooltip = "Download files with the substitution tags";
            $resourceMode = str_replace("[mode]", "No Substitution", $resourceMode);
            $resourceMode = str_replace("[tooltip]", $tooltip, $resourceMode);
            break;
        case 'blank':
            $tooltip = "No tags or data.<br />Use this if you are stocking your paper filing cabinet with a handout";
            $resourceMode = str_replace("[mode]", "Blank", $resourceMode);
            $resourceMode = str_replace("[tooltip]", $tooltip, $resourceMode);
            break;
    }
    $s .= "<div class='alert alert-info' style='[display]'>Some files are not available in the current mode. <a class='alert-link' href='?resource-mode=no_replace'>Click Here to view all files</a></div>"
          .sprintf($resourceMode, ($mode=='replace'?'no_replace':'replace'), ($mode=='replace'?'No Substitution':'Substitution'),
          ($mode=='blank'?'no_replace':'blank'), ($mode=='blank'?'No Substitution':'Blank'));

    if(!$dir_name){
        $s .= "Directory not specified";
        return;
    }
    if( SEEDInput_Str('cmd') == 'download' && ($file = SEEDInput_Str('file')) ) {
        if($mode!="no_replace"){
            $filler = new template_filler($oApp);
            $filler->fill_resource($file);
        }
        else{
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Transfer-Encoding: binary');
            if( ($fp = fopen( $file, "rb" )) ) {
                fpassthru( $fp );
                fclose( $fp );
            }
            die();
        }
        exit;   // actually fill_resource exits, but it's nice to have a reminder of that here
    }

    $oResourcesFiles = new ResourcesFiles( $oApp );

    $folder = str_replace( '/', '', $dir_name );        // resources, handouts, etc, for looking up the related tags

    if(substr_count($dir_name, CATSDIR_RESOURCES) == 0){
        $dir_name = CATSDIR_RESOURCES.$dir_name;
    }
    if(!file_exists($dir_name)){
        $s .= "<h2>Unknown directory $dir_name</h2>";
        return;
    }

    $s .= "<a href='".CATSDIR_DOCUMENTATION."Template%20Format%20Reference.html'>Template Format Reference</a><br />";

    $dir = new DirectoryIterator($dir_name);
    if(iterator_count($dir) == 2){
        $s .= "<h2> No files in directory</h2>";
        return;
    }
    if( !($oClinics = new Clinics($oApp)) || !($iClinic = $oClinics->GetCurrentClinic()) ) {
        return;
    }
    $clients = (new PeopleDB($oApp))->GetList( 'C', $oClinics->IsCoreClinic() ? "" : "clinic='$iClinic'");
    $s .= "<!-- the div that represents the modal dialog -->
            <div class='modal fade' id='file_dialog' role='dialog'>
                <div class='modal-dialog'>
                    <div class='modal-content'>
                        <div class='modal-header'>
                            <h4 class='modal-title'>Please select a client</h4>
                        </div>
                        <div class='modal-body'>
                            <form id='client_form'>
                                <input type='hidden' name='cmd' value='download' />
                                <input type='hidden' name='file' id='file' value='' />
                                <select name='client' required>
                                    <option selected value=''>Select a Client</option>"
                                .SEEDCore_ArrayExpandRows($clients, "<option value='[[_key]]'>[[P_first_name]] [[P_last_name]]</option>")
                                ."</select>
                            </form>
                        </div>
                        <div class='modal-footer'>
                            <input type='submit' value='Download' form='client_form' />
                        </div>
                    </div>
                </div>
            </div>";

    $sFilter = SEEDInput_Str('resource-filter');

    $s .= "<div style='background-color:#def;margin:auto;padding:10px;position:relative;'><form method='post'>"
         ."<input type='text' name='resource-filter' value='$sFilter'/> <input type='submit' value='Filter'/>"
         ."</form></div>";

    $s .= "<table border='0'>";
    foreach ($dir as $fileinfo) {
        if( $fileinfo->isDot() ) continue;
        
        if($mode!='no_replace' && $fileinfo->getExtension()!="docx"){
            $s = str_replace("[display]", "display:inline", $s);
            continue;
        }
        
        if( $sFilter ) {
            if( stripos( $fileinfo->getFilename(), $sFilter ) !== false )  goto found;
            $dbFilename = addslashes($fileinfo->getFilename());
            $dbFilter = addslashes($sFilter);
            if( $oApp->kfdb->Query1( "SELECT _key FROM resources_files "
                                    ."WHERE folder='$folder' AND filename='$dbFilename' AND tags LIKE '%$dbFilter%'" ) ) goto found;
            continue;
        }
        found:
        $oApp->kfdb->SetDebug(0);

        $s .= "<tr>"
                 ."<td valign='top'>"
                     ."<a style='white-space: nowrap' ".downloadPath($mode, $dir_name,$fileinfo)." >"
                         .$fileinfo->getFilename()
                     ."</a>"
                 ."</td>"
                 ."<td style='padding-left:20px' valign='top' data-folder='".SEEDCore_HSC($folder)."' data-filename='".SEEDCore_HSC($fileinfo->getFilename())."'>"
                     .$oResourcesFiles->DrawTags( $folder, $fileinfo->getFilename() )
                 ."</td>"
             ."</tr>";
    }
    $s .= "</table>";
    
    //Replace the display if it has not already been replaced
    $s = str_replace("[display]", "display:none", $s);
    
    $s .= "<script>
            function select_client(file){
                document.getElementById('file').value = file;
                $('#file_dialog').modal('show');
            }
            $(document).ready(function () {
                $(\"#client_form\").on(\"submit\", function() {
                    $('#file_dialog').modal('hide');
                });
                $(\"#file_dialog\").on(\"hidden.bs.modal\", function(){
                    document.getElementById('client_form').reset();
                });
            });
           </script>";

    return( $s );
}

function downloadPath($mode, $dir_name, $fileinfo){
    switch($mode){
        case 'replace':
            return "href='javascript:void(0)' target='_blank' onclick=\"select_client('".$dir_name.$fileinfo->getFilename()."')\"";
        case 'no_replace':
            return "href='?cmd=download&file=".$dir_name.$fileinfo->getFilename()."&resource-mode=no_replace'";
        case 'blank':
            return "href='?cmd=download&file=".$dir_name.$fileinfo->getFilename()."&client=0'";
    }
}

class ResourcesFiles
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    function DrawTags( $folder, $filename )
    {
        $s = "";

        $s .= "<div class='resources-tag resources-tag-new'>+</div>";

        $ra = $this->oApp->kfdb->QueryRA( "SELECT * FROM resources_files WHERE folder='".addslashes($folder)."' AND filename='".addslashes($filename)."'" );
        $raTags = explode( "\t", $ra['tags'] );
        foreach( $raTags as $tag ) {
            if( !$tag ) continue;
            $s .= "<div class='resources-tag'>$tag</div> ";
        }
        return( $s );
    }
}

?>