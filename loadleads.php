<?php	
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
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
        <meta http-equiv="Pragma" content="no-cache">
        <title>GOautodial - Bulk Lead Importer (CSV & XLSX)</title>
        <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
        <?php
			print $ui->standardizedThemeCSS();
			print $ui->creamyThemeCSS();
		?>
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
            /* Lead import page: keep profile sidebar off-canvas; normal copyright footer */
            .wrapper .control-sidebar.control-sidebar-dark {
                position: fixed;
                top: 0;
                right: -230px;
                width: 230px;
                z-index: 1040;
                transition: right 0.3s ease-in-out;
            }
            .wrapper .control-sidebar.control-sidebar-dark.control-sidebar-open {
                right: 0;
            }
            .wrapper .control-sidebar-bg {
                position: fixed;
                z-index: 1035;
                top: 0;
                right: 0;
                width: 0;
                height: 0;
            }
            body.control-sidebar-open .control-sidebar-bg {
                width: 100%;
                height: 100%;
            }
            footer.main-footer {
                clear: both;
            }
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
                </section><!-- /.content -->
            </aside><!-- /.right-side -->
			<?php print $ui->getRightSidebar($user->getUserId(), $user->getUserName(), $user->getUserAvatar()); ?>
        </div><!-- ./wrapper -->

		<?php print $ui->creamyFooter(); ?>

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

                    // If Phone isn't mapped or mapped column is empty, auto-pick best phone column
                    var phoneCol = (mapping.phone_number !== '' && mapping.phone_number != null)
                        ? parseInt(mapping.phone_number, 10) : -1;
                    var phonesFound = phoneCol >= 0 ? countPhoneLikeInColumn(phoneCol, 200) : 0;

                    if (phonesFound < 1) {
                        var autoPhone = findBestPhoneColumn({});
                        if (autoPhone >= 0 && countPhoneLikeInColumn(autoPhone, 200) > 0) {
                            phoneCol = autoPhone;
                            mapping.phone_number = String(autoPhone);
                            $('#map_phone_number').val(String(autoPhone));
                            phonesFound = countPhoneLikeInColumn(phoneCol, 200);
                            phoneMapped = true;
                            renderPreviewTable();
                        }
                    }

                    if (!phoneMapped || phoneCol < 0) {
                        swal('Mapping Error', 'You must map a column to the required "Phone Number" field.', 'error');
                        return;
                    }

                    if (phonesFound < 1) {
                        var samples = [];
                        for (var si = 0; si < Math.min(3, parsedData.length); si++) {
                            samples.push(String(parsedData[si][phoneCol] == null ? '' : parsedData[si][phoneCol]));
                        }
                        var colName = parsedHeaders[phoneCol] || ('column ' + (phoneCol + 1));
                        swal(
                            'No Phone Numbers',
                            'Column "' + colName + '" does not look like phone data.\n' +
                            'Samples: ' + (samples.join(' | ') || '(empty)') + '\n\n' +
                            'In the mapping table, set Phone Number to the spreadsheet column that has numbers like 5551234567, then check the preview.',
                            'error'
                        );
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
                    formData.append('update_existing', '1');
                    formData.append('LeadMapSubmit', '1');

                    // Show loader modal
                    swal({
                        title: "Uploading Leads...",
                        text: "Importing " + phonesFound + " phone row(s) into list " + listId + "…",
                        type: "info",
                        showConfirmButton: false,
                        allowOutsideClick: false
                    });

                    $.ajax({
                        url: './php/AddLoadLeads.php?v=20260329',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        cache: false,
                        success: function(response) {
                            try {
                                var res = typeof response === 'object' ? response : JSON.parse(response);
                                var inserted = parseInt(res.inserted || 0, 10) || 0;
                                var updated = parseInt(res.updated || 0, 10) || 0;
                                var skipped = parseInt(res.skipped || 0, 10) || 0;
                                var detail = res.msg || '';
                                var ok = (inserted > 0 || updated > 0);
                                if (ok) {
                                    swal({
                                        title: "Upload Successful!",
                                        text: detail + "\n\nOpening All Leads…",
                                        type: "success"
                                    }, function() {
                                        window.location.href = './telephonyleads.php?list_id=' + encodeURIComponent(listId);
                                    });
                                } else {
                                    swal({
                                        title: "0 Leads Imported",
                                        text: detail || ("Mapped " + phonesFound + " phones but none were saved. Remap Phone / Name / Email."),
                                        type: "warning"
                                    });
                                }
                            } catch(err) {
                                swal("Error", "Unexpected response from server.", "error");
                            }
                        },
                        error: function(xhr, status, error) {
                            var tip = '';
                            try {
                                var r = JSON.parse(xhr.responseText || '{}');
                                if (r.msg) tip = r.msg;
                            } catch (e) {}
                            swal("Error", tip || ("Failed to upload leads: " + error), "error");
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
                    var colCount = parsedHeaders.length;
                    parsedData = json.slice(1)
                        .filter(row => row && row.length > 0 && row.some(cell => cell !== null && cell !== ''))
                        .map(function(row) {
                            // Pad sparse SheetJS rows so mapped column indexes stay valid
                            var padded = [];
                            for (var i = 0; i < colCount; i++) {
                                padded[i] = (row[i] !== undefined && row[i] !== null) ? row[i] : '';
                            }
                            return padded;
                        });

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

            function cleanHeader(h) {
                return String(h || '').toLowerCase().replace(/[^a-z0-9]/g, '');
            }

            /** Normalize a cell to digits-only phone, or '' if not phone-like. */
            function extractPhoneDigits(raw) {
                var s = String(raw == null ? '' : raw).trim();
                if (!s) return '';
                // Skip addresses / multi-word text (street lines look phone-y after digit strip)
                if (/[a-zA-Z]{2,}/.test(s) && /\s/.test(s)) return '';
                if (/[a-zA-Z]{4,}/.test(s) && !/@/.test(s)) return ''; // names, cities
                if (/^\d+(\.\d+)?e[+\-]?\d+$/i.test(s)) {
                    s = String(Math.round(Number(s)));
                } else if (/^\d+\.0+$/.test(s)) {
                    s = s.replace(/\.0+$/, '');
                }
                var digits = s.replace(/\D+/g, '');
                if (digits.length < 7 || digits.length > 15) return '';
                return digits;
            }

            function countPhoneLikeInColumn(colIdx, sampleLimit) {
                var limit = sampleLimit || 50;
                var hits = 0;
                var n = Math.min(parsedData.length, limit);
                for (var i = 0; i < n; i++) {
                    if (extractPhoneDigits(parsedData[i][colIdx])) hits++;
                }
                return hits;
            }

            /** Best column that actually contains phone numbers (by cell content). */
            function findBestPhoneColumn(exclude) {
                exclude = exclude || {};
                var bestIdx = -1;
                var bestHits = 0;
                parsedHeaders.forEach(function(header, idx) {
                    if (exclude[idx]) return;
                    var hits = countPhoneLikeInColumn(idx);
                    // Prefer header that mentions phone/mobile when scores are close
                    var h = cleanHeader(header);
                    var headerBonus = (h.indexOf('phone') >= 0 || h.indexOf('mobile') >= 0 || h.indexOf('cell') >= 0 || h === 'tel' || h === 'telephone') ? 2 : 0;
                    var score = hits + headerBonus;
                    if (hits > 0 && score > bestHits) {
                        bestHits = score;
                        bestIdx = idx;
                    }
                });
                return bestIdx;
            }

            // Map common Excel headers → CRM fields (phone, name, email, …)
            function headerMatchesField(fieldKey, header) {
                var h = cleanHeader(header);
                var f = cleanHeader(fieldKey);
                if (!h) return false;
                if (h === f) return true;

                var aliases = {
                    phone_number: [
                        'phone', 'phonenumber', 'mobile', 'mobilenumber', 'telephone', 'tel',
                        'cell', 'cellphone', 'contactnumber', 'contactno', 'msisdn',
                        'phone1', 'primaryphone', 'phoneno', 'ph', 'workphone', 'homephone',
                        'cellphone', 'mainphone', 'callerid', 'ani'
                    ],
                    first_name: [
                        'firstname', 'first', 'fname', 'givenname', 'forename',
                        'name', 'fullname', 'fullname', 'customername', 'clientname',
                        'contactname', 'fullname', 'contact'
                    ],
                    last_name: [
                        'lastname', 'last', 'lname', 'surname', 'familyname'
                    ],
                    email: [
                        'email', 'emailaddress', 'emailid', 'mail', 'emailid',
                        'emailaddr', 'primaryemail', 'workemail'
                    ],
                    middle_initial: ['middle', 'middleinitial', 'mi', 'middlename'],
                    alt_phone: ['altphone', 'alternatephone', 'phone2', 'secondaryphone', 'otherphone'],
                    city: ['city', 'town'],
                    state: ['state', 'region', 'st'],
                    postal_code: ['postal', 'postalcode', 'zip', 'zipcode', 'postcode'],
                    country_code: ['country', 'countrycode', 'countryname'],
                    address1: ['address', 'address1', 'addressline1', 'street', 'streetaddress'],
                    address2: ['address2', 'addressline2'],
                    comments: ['comments', 'comment', 'notes', 'note', 'remark', 'remarks'],
                    // Do NOT alias bare "id" / "number" — they steal phone/name columns
                    vendor_lead_code: ['vendor', 'vendorleadcode', 'vendorcode', 'leadcode', 'leadid', 'vendorid'],
                    province: ['province'],
                    gender: ['gender', 'sex'],
                    date_of_birth: ['dateofbirth', 'dob', 'birthday', 'birthdate']
                };

                if (aliases[fieldKey] && aliases[fieldKey].indexOf(h) !== -1) {
                    return true;
                }
                if (fieldKey === 'email' && h.indexOf('email') !== -1) return true;
                if (fieldKey === 'phone_number' && (h.indexOf('phone') !== -1 || h.indexOf('mobile') !== -1 || h.indexOf('cell') !== -1)) return true;
                return false;
            }

            function isFullNameHeader(header) {
                var h = cleanHeader(header);
                return ['name', 'fullname', 'fullname', 'customername', 'clientname', 'contactname', 'fullname', 'contact'].indexOf(h) !== -1;
            }

            function renderMappingTable() {
                var html = '';
                var claimed = {};

                // Resolve phone first from real cell values (headers alone are unreliable)
                var phoneFromData = findBestPhoneColumn({});
                if (phoneFromData >= 0) {
                    claimed[phoneFromData] = true;
                }

                leadFields.forEach(function(field) {
                    var selectId = 'map_' + field.key;
                    html += '<tr>';
                    html += '  <td><strong>' + field.label + '</strong> ' + (field.required ? '<span class="text-danger">*</span>' : '') + '</td>';
                    html += '  <td>';
                    html += '    <select class="form-control mapping-select" id="' + selectId + '" onchange="renderPreviewTable();">';
                    html += '      <option value="">-- Ignore / Do Not Import --</option>';

                    var autoSelected = false;
                    var autoIdx = -1;

                    if (field.key === 'phone_number' && phoneFromData >= 0) {
                        autoIdx = phoneFromData;
                    } else {
                        parsedHeaders.forEach(function(header, idx) {
                            if (claimed[idx]) return;
                            if (headerMatchesField(field.key, header)) {
                                if (field.key === 'last_name' && isFullNameHeader(header)) {
                                    return;
                                }
                                autoIdx = idx;
                            }
                        });

                        if (autoIdx < 0 && field.key === 'first_name') {
                            parsedHeaders.forEach(function(header, idx) {
                                if (claimed[idx]) return;
                                if (isFullNameHeader(header)) autoIdx = idx;
                            });
                        }
                    }

                    parsedHeaders.forEach(function(header, idx) {
                        var selected = '';
                        if (!autoSelected && idx === autoIdx) {
                            selected = 'selected';
                            autoSelected = true;
                            claimed[idx] = true;
                        }
                        var phoneHits = field.key === 'phone_number' ? countPhoneLikeInColumn(idx, 20) : 0;
                        var hint = phoneHits > 0 ? ' (' + phoneHits + ' phones)' : '';
                        html += '      <option value="' + idx + '" ' + selected + '>' + (idx + 1) + '. ' + header + hint + '</option>';
                    });

                    html += '    </select>';
                    html += '  </td>';
                    html += '  <td id="status_' + field.key + '">';
                    if (field.key === 'phone_number' && autoIdx >= 0) {
                        html += '<span class="label label-success">Auto · ' + countPhoneLikeInColumn(autoIdx) + ' phones</span>';
                    } else {
                        html += field.required ? '<span class="label label-danger">Required</span>' : '<span class="label label-default">Optional</span>';
                    }
                    html += '  </td>';
                    html += '</tr>';
                });

                $('#mapping_rows').html(html);
                renderPreviewTable();
            }

            function renderPreviewTable() {
                if (parsedData.length === 0) return;

                var thead = '<tr>';
                var activeFields = [];
                leadFields.forEach(function(field) {
                    var colIdx = $('#map_' + field.key).val();
                    if (colIdx !== '' && colIdx !== null) {
                        thead += '<th>' + field.label + '</th>';
                        activeFields.push({ key: field.key, colIdx: parseInt(colIdx, 10) });
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
                var fullNameIdx = null;

                // If first_name maps to a Full Name column and last_name is empty, split on import
                if (mapping.first_name !== '' && mapping.first_name != null &&
                    (mapping.last_name === '' || mapping.last_name == null)) {
                    var h = parsedHeaders[parseInt(mapping.first_name, 10)];
                    if (isFullNameHeader(h)) {
                        fullNameIdx = parseInt(mapping.first_name, 10);
                    }
                }

                leadFields.forEach(function(field) {
                    var idx = mapping[field.key];
                    if (idx !== '' && idx !== null && idx !== undefined) {
                        headers.push(field.key);
                        colIndices.push({ key: field.key, idx: parseInt(idx, 10) });
                    }
                });

                // Ensure first_name + last_name columns exist when splitting a Name column
                if (fullNameIdx !== null) {
                    if (headers.indexOf('first_name') === -1) {
                        headers.push('first_name');
                        colIndices.push({ key: 'first_name', idx: fullNameIdx, split: 'first' });
                    } else {
                        colIndices.forEach(function(item) {
                            if (item.key === 'first_name') item.split = 'first';
                        });
                    }
                    if (headers.indexOf('last_name') === -1) {
                        headers.push('last_name');
                        colIndices.push({ key: 'last_name', idx: fullNameIdx, split: 'last' });
                    }
                }

                function csvEscape(val) {
                    val = String(val == null ? '' : val).trim();
                    if (val.indexOf(',') !== -1 || val.indexOf('"') !== -1 || val.indexOf('\n') !== -1) {
                        val = '"' + val.replace(/"/g, '""') + '"';
                    }
                    return val;
                }

                var csvLines = [headers.join(',')];

                data.forEach(function(row) {
                    var line = colIndices.map(function(item) {
                        var val = (row[item.idx] !== undefined && row[item.idx] !== null) ? String(row[item.idx]).trim() : '';
                        // Coerce Excel scientific / float phones for the phone columns
                        if ((item.key === 'phone_number' || item.key === 'alt_phone') && val !== '') {
                            var digits = extractPhoneDigits(val);
                            if (digits) {
                                val = digits;
                            } else if (/^\d+(\.\d+)?e[+\-]?\d+$/i.test(val)) {
                                val = String(Math.round(Number(val)));
                            } else if (/^\d+\.0+$/.test(val)) {
                                val = val.replace(/\.0+$/, '');
                            }
                        }
                        if (item.split === 'first' || item.split === 'last') {
                            var parts = val.split(/\s+/).filter(Boolean);
                            if (item.split === 'first') {
                                val = parts.length ? parts[0] : '';
                            } else {
                                val = parts.length > 1 ? parts.slice(1).join(' ') : '';
                            }
                        }
                        return csvEscape(val);
                    });
                    csvLines.push(line.join(','));
                });

                return csvLines.join('\n');
            }
		</script>
    </body>
</html>
