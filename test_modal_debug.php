<?php
require_once('./php/UIHandler.php');
require_once('./php/APIHandler.php');
require_once('./php/CRMDefaults.php');
require_once('./php/LanguageHandler.php');
include('./php/Session.php');
$ui = \creamy\UIHandler::getInstance();
$lh = \creamy\LanguageHandler::getInstance();
$user = \creamy\CreamyUser::currentUser();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Modal Debug Test</title>
<?php print $ui->standardizedThemeCSS(); ?>
<script src="adminlte/colorpicker/bootstrap-colorpicker.min.js"></script>
</head>
<?php print $ui->creamyBody(); ?>
<div class="wrapper">
<?php print $ui->creamyHeader($user); ?>
<div class="content-wrapper" style="padding:30px;">
<h2>Modal Debug Test</h2>

<button type="button" id="test-btn" class="btn btn-primary btn-lg" onclick="$('#add_campaign').modal('show');">
    <i class="fa fa-plus"></i> Open Add Campaign Modal (onclick)
</button>

<button type="button" id="test-btn2" class="btn btn-success btn-lg" data-toggle="modal" data-target="#add_campaign" style="margin-left:10px;">
    <i class="fa fa-plus"></i> Open Add Campaign Modal (data-toggle)
</button>

<div id="debug-output" style="margin-top:20px;padding:10px;background:#f5f5f5;border:1px solid #ddd;">
Loading debug info...
</div>
</div>
</div>

<!-- Campaign Modal (simplified) -->
<div id="add_campaign" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Add Campaign Modal - WORKS!</h4>
      </div>
      <div class="modal-body">
        <p style="color:green;font-size:20px;">✅ The modal is working!</p>
        <p>Now you can proceed with the full campaign form.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script>
window.onerror = function(msg, url, lineNo, columnNo, error) {
    var err = "Error: " + msg + " at line " + lineNo;
    $('#debug-output').append('<br><span style="color:red">' + err + '</span>');
    return false;
};

$(document).ready(function(){
    var debug = '';
    debug += 'jQuery: ' + (typeof $ !== 'undefined' ? '✅ v' + $.fn.jquery : '❌ MISSING') + '<br>';
    debug += 'Bootstrap modal: ' + (typeof $.fn.modal !== 'undefined' ? '✅' : '❌ MISSING - This is why buttons fail!') + '<br>';
    debug += 'jquery.steps: ' + (typeof $.fn.steps !== 'undefined' ? '✅' : '❌ MISSING') + '<br>';
    debug += 'colorpicker: ' + (typeof $.fn.colorpicker !== 'undefined' ? '✅' : '❌ MISSING') + '<br>';
    debug += 'select2: ' + (typeof $.fn.select2 !== 'undefined' ? '✅' : '❌ MISSING') + '<br>';
    $('#debug-output').html(debug);

    // Test manual trigger
    $('#test-btn').click(function(){
        $('#debug-output').append('<br>Button clicked!');
        console.log('onclick button clicked');
        try {
            $('#add_campaign').modal('show');
            $('#debug-output').append('<br>Modal triggered.');
        } catch(e) {
            $('#debug-output').append('<br><span style="color:red">Exception: ' + e.message + '</span>');
        }
    });
});
</script>
</body>
</html>
