<?php
require_once __DIR__ . '/php/RequestGuard.php';
	
	require_once('./php/UIHandler.php');
	require_once('./php/APIHandler.php');
	require_once('./php/CRMDefaults.php');
    require_once('./php/LanguageHandler.php');
    include('./php/Session.php');

	$ui = \creamy\UIHandler::getInstance();
	$api = \creamy\APIHandler::getInstance();
	$lh = \creamy\LanguageHandler::getInstance();
	$user = \creamy\CreamyUser::currentUser();

	// Redirect non-admin users
	if ($user->getUserRole() != CRM_DEFAULTS_USER_ROLE_ADMIN) {
		header("location: agent.php");
		exit;
	}

	// Fetch Campaigns and Lists via API
	$campaigns = $api->API_getAllCampaigns();
	$lists = $api->API_getAllLists();
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>GOautodial - Bulk Lead Importer (CSV & XLSX)</title>
        <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
        <link href="css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="css/creamycrm.css" rel="stylesheet" type="text/css" />
        <?php print $ui->creamyThemeCSS(); ?>
    	<link href="css/circle-buttons.css" rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="css/customizedLoader.css">
        <link rel="stylesheet" href="js/dashboard/sweetalert/dist/sweetalert.css">

        <script src="js/jquery.min.js"></script>
        <script src="js/bootstrap.min.js" type="text/javascript"></script>
        <script src="js/app.min.js" type="text/javascript"></script>
        <script src="js/dashboard/sweetalert/dist/sweetalert.min.js"></script>
        
        <!-- SheetJS Library for CSV/XLSX/XLS Parsing -->
        <script src="js/xlsx.full.min.js"></script>
        <script>
            if (typeof XLSX === 'undefined') {
                document.write('<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"><\/script>');
            }
        </script>

        <style>
            .import-card {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.08);
                padding: 25px;
                margin-bottom: 25px;
            }
            .step-header {
                font-size: 16px;
                font-weight: 600;
                color: #2c3e50;
                border-bottom: 2px solid #3c8dbc;
                padding-bottom: 8px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
            }
            .step-badge {
                background: #3c8dbc;
                color: #fff;
                border-radius: 50%;
                width: 28px;
                height: 28px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-right: 10px;
                font-size: 14px;
            }
            .drop-zone {
                border: 2px dashed #b4c6d0;
                border-radius: 6px;
                padding: 30px;
                text-align: center;
                background: #f8fafc;
                cursor: pointer;
                transition: all 0.2s ease-in-out;
            }
            .drop-zone:hover, .drop-zone.dragover {
                border-color: #3c8dbc;
                background: #ebf5fb;
            }
            .drop-zone i {
                font-size: 42px;
                color: #3c8dbc;
                margin-bottom: 10px;
            }
            .mapping-table th {
                background: #f1f5f9;
                color: #334155;
            }
            .preview-table {
                font-size: 12px;
            }
            .badge-file-type {
                font-size: 12px;
                padding: 5px 10px;
                border-radius: 4px;
                font-weight: 600;
            }
            .badge-csv { background: #27ae60; color: #fff; }
            .badge-xlsx { background: #2980b9; color: #fff; }
        </style>

        <script type="text/javascript">
			$(window).ready(function() {
				$(".preloader").fadeOut("slow");
			});
		</script>
    </head>
    <?php print $ui->creamyBody(); ?>
        <div class="wrapper">
            <?php print $ui->creamyHeader($user); ?>
			<?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

            <aside class="right-side">
                <section class="content-header">
                    <h1>
                        Lead Import Tool
                        <small>(Bulk Upload CSV & XLSX to Campaign)</small>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="./index.php"><i class="fa fa-phone"></i> <?php $lh->translateText("home"); ?></a></li>
                        <li>Telephony</li>
						<li class="active">Upload Leads</li>
                    </ol>
                </section>
		
                <section class="content">
                <?php if ($user->userHasAdminPermission()) { ?>
                    
                    <form id="lead_import_form" enctype="multipart/form-data">
                        
                        <!-- STEP 1: Select Destination -->
                        <div class="import-card">
                            <div class="step-header">
                                <span class="step-badge">1</span> Select Target Campaign & List
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="campaign_select">Select Campaign:</label>
                                        <select class="form-control" id="campaign_select" name="campaign_id">
                                            <option value="">-- All Campaigns / Select Campaign --</option>
                                            <?php 
                                            if (isset($campaigns->campaign_id) && is_array($campaigns->campaign_id)) {
                                                for ($i = 0; $i < count($campaigns->campaign_id); $i++) {
                                                    echo '<option value="'.$campaigns->campaign_id[$i].'">'.$campaigns->campaign_id[$i].' - '.$campaigns->campaign_name[$i].'</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <span class="help-block"><small>Selecting a campaign will filter the lists below.</small></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="list_select">Select Target List ID <span class="text-danger">*</span>:</label>
                                        <select class="form-control" id="list_select" name="list_id" required>
                                            <option value="">-- Select List ID --</option>
                                            <?php 
                                            if (isset($lists->list_id) && is_array($lists->list_id)) {
                                                for ($i = 0; $i < count($lists->list_id); $i++) {
                                                    $camp_attr = isset($lists->campaign_id[$i]) ? $lists->campaign_id[$i] : '';
                                                    $list_name = isset($lists->list_name[$i]) ? $lists->list_name[$i] : '';
                                                    echo '<option value="'.$lists->list_id[$i].'" data-campaign="'.$camp_attr.'">'.$lists->list_id[$i].' - '.$list_name.' (Campaign: '.$camp_attr.')</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 2: File Upload -->
                        <div class="import-card">
                            <div class="step-header">
                                <span class="step-badge">2</span> Upload Leads File (.CSV, .XLSX, .XLS)
                            </div>
                            
                            <div class="drop-zone" id="drop_zone">
                                <i class="fa fa-cloud-upload"></i>
                                <h4 style="margin-top:0;">Drag & Drop your CSV or Excel file here</h4>
                                <p class="text-muted">Supports <strong>.csv</strong>, <strong>.xlsx</strong>, and <strong>.xls</strong> files</p>
                                <button type="button" class="btn btn-primary btn-sm" onclick="$('#file_upload_input').click();">
                                    <i class="fa fa-folder-open"></i> Browse File
                                </button>
                                <input type="file" id="file_upload_input" accept=".csv, .xlsx, .xls" style="display:none;" />
                            </div>

                            <div id="file_info_panel" style="display:none; margin-top: 15px;" class="alert alert-info">
                                <div class="row" style="display:flex; align-items:center;">
                                    <div class="col-xs-8">
                                        <strong id="file_info_name">file.xlsx</strong>
                                        <span id="file_info_type" class="badge-file-type badge-xlsx margin-left">XLSX</span>
                                        <span id="file_info_count" class="margin-left" style="margin-left:15px; font-weight:bold;">0 rows detected</span>
                                    </div>
                                    <div class="col-xs-4 text-right">
                                        <button type="button" class="btn btn-default btn-xs" onclick="resetFile();">
                                            <i class="fa fa-times text-danger"></i> Remove File
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 3: Mapping & Settings (Hidden until file selected) -->
                        <div id="mapping_and_preview_section" style="display:none;">
                            
                            <!-- Mapping Section -->
                            <div class="import-card">
                                <div class="step-header">
                                    <span class="step-badge">3</span> Column Mapping
                                </div>
                                <p class="text-muted">Map headers from your spreadsheet to the corresponding GOautodial lead fields:</p>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped mapping-table">
                                        <thead>
                                            <tr>
                                                <th width="35%">GOautodial Lead Field</th>
                                                <th width="45%">Spreadsheet Header / Column</th>
                                                <th width="20%">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="mapping_rows">
                                            <!-- Dynamic mapping rows populated by JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Options Section -->
                            <div class="import-card">
                                <div class="step-header">
                                    <span class="step-badge">4</span> Import Options
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Duplicate Check Strategy:</label>
                                            <select class="form-control" name="goDupcheck" id="dup_check">
                                                <option value="CHECK_NUM_AND_LIST" selected>Check Phone Number in List (Recommended)</option>
                                                <option value="CHECK_NUM_LIST_AND_SYSTEM">Check Phone Number in List & System</option>
                                                <option value="NONE">Do Not Check Duplicates (Allow All)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Country / Phone Code Override:</label>
                                            <input type="text" class="form-control" name="phone_code_override" id="phone_code_override" value="1" placeholder="e.g. 1 for US/Canada">
                                            <span class="help-block"><small>Automatically prefixed if lead phone number has no country code.</small></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Preview Section -->
                            <div class="import-card">
                                <div class="step-header">
                                    <span class="step-badge">5</span> Data Preview (First 5 Rows)
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-condensed table-bordered preview-table" id="preview_table">
                                        <thead id="preview_thead"></thead>
                                        <tbody id="preview_tbody"></tbody>
                                    </table>
                                </div>

                                <div class="row" style="margin-top: 25px;">
                                    <div class="col-md-12 text-right">
                                        <button type="submit" id="submit_import_btn" class="btn btn-success btn-lg">
                                            <i class="fa fa-upload"></i> Start Lead Import
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>

				<?php } else { print $ui->calloutErrorMessage($lh->translationFor("you_dont_have_permission")); } ?>
                </section>
            </aside>
			<?php print $ui->getRightSidebar($user->getUserId(), $user->getUserName(), $user->getUserAvatar()); ?>
        </div>

		<script type="text/javascript">
            // Lead fields definition
            var leadFields = [
                { key: 'phone_number', label: 'Phone Number', required: true },
                { key: 'first_name', label: 'First Name', required: false },
                { key: 'last_name', label: 'Last Name', required: false },
                { key: 'middle_initial', label: 'Middle Initial', required: false },
                { key: 'address1', label: 'Address Line 1', required: false },
                { key: 'address2', label: 'Address Line 2', required: false },
                { key: 'city', label: 'City', required: false },
                { key: 'state', label: 'State / Region', required: false },
                { key: 'postal_code', label: 'Postal / Zip Code', required: false },
                { key: 'country_code', label: 'Country Code', required: false },
                { key: 'email', label: 'Email Address', required: false },
                { key: 'comments', label: 'Comments / Notes', required: false },
                { key: 'vendor_lead_code', label: 'Vendor Lead Code', required: false },
                { key: 'alt_phone', label: 'Alt Phone Number', required: false },
                { key: 'province', label: 'Province', required: false },
                { key: 'gender', label: 'Gender', required: false },
                { key: 'date_of_birth', label: 'Date of Birth', required: false },
                { key: 'security_phrase', label: 'Security Phrase', required: false }
            ];

            var parsedData = [];
            var parsedHeaders = [];
            var selectedFile = null;

			$(document).ready(function() {
				
                // Campaign filter trigger
                $('#campaign_select').change(function() {
                    var selectedCampaign = $(this).val();
                    if (!selectedCampaign) {
                        $('#list_select option').show();
                    } else {
                        $('#list_select option').each(function() {
                            var camp = $(this).data('campaign');
                            if ($(this).val() === "" || camp === selectedCampaign) {
                                $(this).show();
                            } else {
                                $(this).hide();
                            }
                        });
                    }
                    $('#list_select').val('');
                });

                // Drag & Drop handlers
                var dropZone = document.getElementById('drop_zone');
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, preventDefaults, false);
                });
                function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
                });
                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
                });

                dropZone.addEventListener('drop', function(e) {
                    var dt = e.dataTransfer;
                    var files = dt.files;
                    if (files.length > 0) {
                        handleFileSelection(files[0]);
                    }
                });

                $('#file_upload_input').change(function(e) {
                    if (e.target.files.length > 0) {
                        handleFileSelection(e.target.files[0]);
                    }
                });

                // Form submit handler
                $('#lead_import_form').submit(function(e) {
                    e.preventDefault();
                    
                    var listId = $('#list_select').val();
                    if (!listId) {
                        swal('Missing List ID', 'Please select a target List ID to upload leads into.', 'warning');
                        return;
                    }
                    if (parsedData.length === 0) {
                        swal('No Data', 'Please select a valid CSV or XLSX file containing lead data.', 'warning');
                        return;
                    }

                    // Build mapped dataset
                    var mapping = {};
                    var phoneMapped = false;

                    leadFields.forEach(function(field) {
                        var val = $('#map_' + field.key).val();
                        mapping[field.key] = val;
                        if (field.key === 'phone_number' && val !== '') {
                            phoneMapped = true;
                        }
                    });

                    if (!phoneMapped) {
                        swal('Mapping Error', 'You must map a column to the required "Phone Number" field.', 'error');
                        return;
                    }

                    // Convert mapped rows to CSV Blob
                    var csvContent = generateCSVFromData(parsedData, mapping);
                    var csvBlob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });

                    var formData = new FormData();
                    formData.append('file_upload', csvBlob, 'import_leads.csv');
                    formData.append('list_id', listId);
                    formData.append('goDupcheck', $('#dup_check').val());
                    formData.append('phone_code_override', $('#phone_code_override').val());
                    formData.append('LeadMapSubmit', '1');

                    // Show loader modal
                    swal({
                        title: "Uploading Leads...",
                        text: "Please wait while your leads are imported into the campaign list.",
                        type: "info",
                        showConfirmButton: false,
                        allowOutsideClick: false
                    });

                    $.ajax({
                        url: './php/AddLoadLeads.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            try {
                                var res = typeof response === 'object' ? response : JSON.parse(response);
                                if (res.result === 'success' || res.result === '1' || res.result === 1) {
                                    var dupsText = res.dups ? ' (Duplicates Skipped: ' + res.dups + ')' : '';
                                    var insertedText = res.inserted ? res.inserted : parsedData.length;
                                    swal({
                                        title: "Upload Successful!",
                                        text: "Successfully processed leads for List " + listId + ".\n" + res.msg + dupsText,
                                        type: "success"
                                    }, function() {
                                        window.location.reload();
                                    });
                                } else {
                                    swal("Upload Notice", res.msg || "Leads submitted to dialer.", "info");
                                }
                            } catch(err) {
                                swal("Success!", "Lead import request sent successfully.", "success");
                            }
                        },
                        error: function(xhr, status, error) {
                            swal("Error", "Failed to upload leads: " + error, "error");
                        }
                    });
                });

			});

            function handleFileSelection(file) {
                selectedFile = file;
                var fileName = file.name;
                var ext = fileName.split('.').pop().toLowerCase();

                if (['csv', 'xlsx', 'xls'].indexOf(ext) === -1) {
                    swal('Invalid File Type', 'Please upload a .csv, .xlsx, or .xls file.', 'error');
                    return;
                }

                $('#file_info_name').text(fileName);
                $('#file_info_type').text(ext.toUpperCase());
                if (ext === 'csv') {
                    $('#file_info_type').removeClass('badge-xlsx').addClass('badge-csv');
                } else {
                    $('#file_info_type').removeClass('badge-csv').addClass('badge-xlsx');
                }

                var reader = new FileReader();
                reader.onload = function(e) {
                    var data = e.target.result;
                    var workbook;
                    if (ext === 'csv') {
                        workbook = XLSX.read(data, { type: 'string' });
                    } else {
                        var arr = new Uint8Array(data);
                        workbook = XLSX.read(arr, { type: 'array' });
                    }

                    var firstSheetName = workbook.SheetNames[0];
                    var worksheet = workbook.Sheets[firstSheetName];
                    var json = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                    if (json.length < 2) {
                        swal('Empty File', 'The selected file does not contain header and data rows.', 'warning');
                        return;
                    }

                    parsedHeaders = json[0].map(h => String(h || '').trim());
                    parsedData = json.slice(1).filter(row => row && row.length > 0 && row.some(cell => cell !== null && cell !== ''));

                    $('#file_info_count').text(parsedData.length + ' rows detected');
                    $('#file_info_panel').slideDown();

                    renderMappingTable();
                    renderPreviewTable();
                    $('#mapping_and_preview_section').slideDown();
                };

                if (ext === 'csv') {
                    reader.readAsText(file);
                } else {
                    reader.readAsArrayBuffer(file);
                }
            }

            function resetFile() {
                selectedFile = null;
                parsedData = [];
                parsedHeaders = [];
                $('#file_upload_input').val('');
                $('#file_info_panel').slideUp();
                $('#mapping_and_preview_section').slideUp();
            }

            function renderMappingTable() {
                var html = '';
                leadFields.forEach(function(field) {
                    var selectId = 'map_' + field.key;
                    html += '<tr>';
                    html += '  <td><strong>' + field.label + '</strong> ' + (field.required ? '<span class="text-danger">*</span>' : '') + '</td>';
                    html += '  <td>';
                    html += '    <select class="form-control mapping-select" id="' + selectId + '" onchange="renderPreviewTable();">';
                    html += '      <option value="">-- Ignore / Do Not Import --</option>';

                    // Auto match column name
                    var autoSelected = false;
                    parsedHeaders.forEach(function(header, idx) {
                        var cleanH = header.toLowerCase().replace(/[^a-z0-9]/g, '');
                        var cleanF = field.key.toLowerCase().replace(/[^a-z0-9]/g, '');
                        var match = (cleanH === cleanF) || 
                                    (cleanF === 'phonenumber' && (cleanH === 'phone' || cleanH === 'mobile' || cleanH === 'telephone' || cleanH === 'cell')) ||
                                    (cleanF === 'firstname' && (cleanH === 'first' || cleanH === 'name')) ||
                                    (cleanF === 'lastname' && cleanH === 'last') ||
                                    (cleanF === 'postalcode' && (cleanH === 'zip' || cleanH === 'zipcode'));

                        var selected = (match && !autoSelected) ? 'selected' : '';
                        if (selected) autoSelected = true;

                        html += '      <option value="' + idx + '" ' + selected + '>' + (idx + 1) + '. ' + header + '</option>';
                    });

                    html += '    </select>';
                    html += '  </td>';
                    html += '  <td id="status_' + field.key + '">';
                    html += field.required ? '<span class="label label-danger">Required</span>' : '<span class="label label-default">Optional</span>';
                    html += '  </td>';
                    html += '</tr>';
                });

                $('#mapping_rows').html(html);
            }

            function renderPreviewTable() {
                if (parsedData.length === 0) return;

                var thead = '<tr>';
                var activeFields = [];
                leadFields.forEach(function(field) {
                    var colIdx = $('#map_' + field.key).val();
                    if (colIdx !== '' && colIdx !== null) {
                        thead += '<th>' + field.label + '</th>';
                        activeFields.push({ key: field.key, colIdx: parseInt(colIdx) });
                    }
                });
                thead += '</tr>';
                $('#preview_thead').html(thead);

                var tbody = '';
                var sampleRows = parsedData.slice(0, 5);
                sampleRows.forEach(function(row) {
                    tbody += '<tr>';
                    activeFields.forEach(function(af) {
                        var val = (row[af.colIdx] !== undefined && row[af.colIdx] !== null) ? row[af.colIdx] : '';
                        tbody += '<td>' + String(val).trim() + '</td>';
                    });
                    tbody += '</tr>';
                });
                $('#preview_tbody').html(tbody);
            }

            function generateCSVFromData(data, mapping) {
                var headers = [];
                var colIndices = [];

                leadFields.forEach(function(field) {
                    var idx = mapping[field.key];
                    if (idx !== '' && idx !== null && idx !== undefined) {
                        headers.push(field.key);
                        colIndices.push({ key: field.key, idx: parseInt(idx) });
                    }
                });

                var csvLines = [headers.join(',')];

                data.forEach(function(row) {
                    var line = colIndices.map(function(item) {
                        var val = (row[item.idx] !== undefined && row[item.idx] !== null) ? String(row[item.idx]).trim() : '';
                        // Escape CSV quotes and commas
                        if (val.indexOf(',') !== -1 || val.indexOf('"') !== -1 || val.indexOf('\n') !== -1) {
                            val = '"' + val.replace(/"/g, '""') + '"';
                        }
                        return val;
                    });
                    csvLines.push(line.join(','));
                });

                return csvLines.join('\n');
            }
		</script>
    </body>
</html>
