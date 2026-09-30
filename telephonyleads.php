<?php
/**
 * @file        telephonyleads.php
 * @brief       Admin table of all uploaded leads (vicidial_list) with edit/delete
 */
require_once('./php/UIHandler.php');
require_once('./php/APIHandler.php');
require_once('./php/CRMDefaults.php');
require_once('./php/LanguageHandler.php');
require_once('./php/Config.php');
include('./php/Session.php');

$ui = \creamy\UIHandler::getInstance();
$api = \creamy\APIHandler::getInstance();
$lh = \creamy\LanguageHandler::getInstance();
$user = \creamy\CreamyUser::currentUser();

if ($user->getUserRole() != CRM_DEFAULTS_USER_ROLE_ADMIN) {
	header('location: agent.php');
	exit;
}

$filterList = isset($_GET['list_id']) ? preg_replace('/[^0-9]/', '', (string) $_GET['list_id']) : '';

$listOptions = array();
$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$dbUser = defined('DB_USERNAME') ? DB_USERNAME : 'root';
$dbPass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
$port = defined('DB_PORT') ? (int) DB_PORT : 3306;
$dbName = defined('DB_NAME_ASTERISK') ? DB_NAME_ASTERISK : 'asterisk';
$mysqli = @new mysqli($host, $dbUser, $dbPass, $dbName, $port);
if (!$mysqli->connect_errno) {
	$r = $mysqli->query('SELECT list_id, list_name FROM vicidial_lists ORDER BY list_id');
	if ($r) {
		while ($row = $r->fetch_assoc()) {
			$listOptions[] = $row;
		}
	}
	$mysqli->close();
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php $lh->translateText('portal_title'); ?> - Leads</title>
	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
	<?php
		print $ui->standardizedThemeCSS();
		print $ui->creamyThemeCSS();
		print $ui->dataTablesTheme();
	?>
	<script src="js/app.min.js" type="text/javascript"></script>
	<script src="js/dashboard/sweetalert/dist/sweetalert.min.js"></script>
	<script type="text/javascript">
		$(window).ready(function() {
			$(".preloader").fadeOut("slow");
		});
	</script>
	<style>
		.lead-actions .btn { margin: 0 2px; }
		#btn_delete_all { margin-top: 0; }
	</style>
</head>
<?php print $ui->creamyBody(); ?>
	<div class="wrapper">
		<?php print $ui->creamyHeader($user); ?>
		<?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

		<aside class="right-side">
			<section class="content-header">
				<h1>
					All Leads
					<small>CSV / Excel contacts stored in vicidial_list</small>
				</h1>
				<ol class="breadcrumb">
					<li><a href="./index.php"><i class="fa fa-home"></i> <?php $lh->translateText('home'); ?></a></li>
					<li><?php $lh->translateText('telephony'); ?></li>
					<li class="active">Leads</li>
				</ol>
			</section>

			<section class="content">
				<div class="panel panel-default">
					<div class="panel-body">
						<div class="row">
							<div class="col-sm-3">
								<label>List</label>
								<select id="filter_list" class="form-control">
									<option value="">All lists</option>
									<?php foreach ($listOptions as $opt) { ?>
									<option value="<?php echo htmlspecialchars($opt['list_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filterList !== '' && $filterList == $opt['list_id']) ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($opt['list_id'] . ' — ' . $opt['list_name'], ENT_QUOTES, 'UTF-8'); ?>
									</option>
									<?php } ?>
								</select>
							</div>
							<div class="col-sm-2">
								<label>Status</label>
								<select id="filter_status" class="form-control">
									<option value="">All statuses</option>
									<option value="NEW">NEW</option>
									<option value="SALE">SALE</option>
									<option value="DNC">DNC</option>
									<option value="A">A</option>
									<option value="N">N</option>
									<option value="NA">NA</option>
									<option value="B">B</option>
									<option value="DROP">DROP</option>
								</select>
							</div>
							<div class="col-sm-3">
								<label>Search</label>
								<input type="text" id="filter_q" class="form-control" placeholder="Phone, name, email, lead ID…">
							</div>
							<div class="col-sm-2">
								<label>&nbsp;</label>
								<button type="button" id="btn_reload" class="btn btn-primary btn-block"><i class="fa fa-refresh"></i> Refresh</button>
							</div>
							<div class="col-sm-2">
								<label>&nbsp;</label>
								<button type="button" id="btn_delete_all" class="btn btn-danger btn-block"><i class="fa fa-trash"></i> Delete All</button>
							</div>
						</div>
						<p class="text-muted" style="margin:15px 0 10px;">
							<span id="leads_summary">Loading…</span>
							&nbsp;·&nbsp;
							<a href="./loadleads.php">Upload Leads</a>
							&nbsp;·&nbsp;
							<a href="./telephonylist.php">Lists</a>
						</p>
						<table id="table_leads" class="table table-bordered table-striped display responsive nowrap" width="100%">
							<thead>
								<tr>
									<th>Lead ID</th>
									<th>List</th>
									<th>Phone</th>
									<th>Name</th>
									<th>Email</th>
									<th>City</th>
									<th>State</th>
									<th>Status</th>
									<th>Vendor</th>
									<th>Entry Date</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</section>
		</aside>
		<?php print $ui->getRightSidebar($user->getUserId(), $user->getUserName(), $user->getUserAvatar()); ?>
	</div>

	<!-- Edit Lead Modal -->
	<div class="modal fade" id="editLeadModal" tabindex="-1" role="dialog">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<form id="edit_lead_form">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
						<h4 class="modal-title">Edit Lead <span id="edit_lead_id_label"></span></h4>
					</div>
					<div class="modal-body">
						<input type="hidden" name="lead_id" id="edit_lead_id">
						<div class="row">
							<div class="col-sm-4 form-group">
								<label>List ID *</label>
								<select name="list_id" id="edit_list_id" class="form-control" required>
									<?php foreach ($listOptions as $opt) { ?>
									<option value="<?php echo htmlspecialchars($opt['list_id'], ENT_QUOTES, 'UTF-8'); ?>">
										<?php echo htmlspecialchars($opt['list_id'] . ' — ' . $opt['list_name'], ENT_QUOTES, 'UTF-8'); ?>
									</option>
									<?php } ?>
								</select>
							</div>
							<div class="col-sm-4 form-group">
								<label>Phone *</label>
								<input type="text" name="phone_number" id="edit_phone_number" class="form-control" required>
							</div>
							<div class="col-sm-2 form-group">
								<label>Phone Code</label>
								<input type="text" name="phone_code" id="edit_phone_code" class="form-control" value="1">
							</div>
							<div class="col-sm-2 form-group">
								<label>Status</label>
								<select name="status" id="edit_status" class="form-control">
									<option value="NEW">NEW</option>
									<option value="SALE">SALE</option>
									<option value="DNC">DNC</option>
									<option value="A">A</option>
									<option value="N">N</option>
									<option value="NA">NA</option>
									<option value="B">B</option>
									<option value="DROP">DROP</option>
								</select>
							</div>
						</div>
						<div class="row">
							<div class="col-sm-2 form-group">
								<label>Title</label>
								<input type="text" name="title" id="edit_title" class="form-control" maxlength="4">
							</div>
							<div class="col-sm-4 form-group">
								<label>First Name</label>
								<input type="text" name="first_name" id="edit_first_name" class="form-control">
							</div>
							<div class="col-sm-2 form-group">
								<label>MI</label>
								<input type="text" name="middle_initial" id="edit_middle_initial" class="form-control" maxlength="1">
							</div>
							<div class="col-sm-4 form-group">
								<label>Last Name</label>
								<input type="text" name="last_name" id="edit_last_name" class="form-control">
							</div>
						</div>
						<div class="row">
							<div class="col-sm-6 form-group">
								<label>Email</label>
								<input type="text" name="email" id="edit_email" class="form-control">
							</div>
							<div class="col-sm-3 form-group">
								<label>Alt Phone</label>
								<input type="text" name="alt_phone" id="edit_alt_phone" class="form-control">
							</div>
							<div class="col-sm-3 form-group">
								<label>Vendor Code</label>
								<input type="text" name="vendor_lead_code" id="edit_vendor_lead_code" class="form-control">
							</div>
						</div>
						<div class="row">
							<div class="col-sm-6 form-group">
								<label>Address 1</label>
								<input type="text" name="address1" id="edit_address1" class="form-control">
							</div>
							<div class="col-sm-3 form-group">
								<label>Address 2</label>
								<input type="text" name="address2" id="edit_address2" class="form-control">
							</div>
							<div class="col-sm-3 form-group">
								<label>Address 3</label>
								<input type="text" name="address3" id="edit_address3" class="form-control">
							</div>
						</div>
						<div class="row">
							<div class="col-sm-3 form-group">
								<label>City</label>
								<input type="text" name="city" id="edit_city" class="form-control">
							</div>
							<div class="col-sm-2 form-group">
								<label>State</label>
								<input type="text" name="state" id="edit_state" class="form-control" maxlength="2">
							</div>
							<div class="col-sm-3 form-group">
								<label>Province</label>
								<input type="text" name="province" id="edit_province" class="form-control">
							</div>
							<div class="col-sm-2 form-group">
								<label>Postal</label>
								<input type="text" name="postal_code" id="edit_postal_code" class="form-control">
							</div>
							<div class="col-sm-2 form-group">
								<label>Country</label>
								<input type="text" name="country_code" id="edit_country_code" class="form-control" maxlength="3">
							</div>
						</div>
						<div class="row">
							<div class="col-sm-2 form-group">
								<label>Gender</label>
								<select name="gender" id="edit_gender" class="form-control">
									<option value="U">U</option>
									<option value="M">M</option>
									<option value="F">F</option>
								</select>
							</div>
							<div class="col-sm-3 form-group">
								<label>Date of Birth</label>
								<input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control">
							</div>
							<div class="col-sm-3 form-group">
								<label>Security Phrase</label>
								<input type="text" name="security_phrase" id="edit_security_phrase" class="form-control">
							</div>
							<div class="col-sm-4 form-group">
								<label>Comments</label>
								<input type="text" name="comments" id="edit_comments" class="form-control">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-primary" id="btn_save_lead">Save Changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>

<?php print $ui->creamyFooter(); ?>
<script type="text/javascript">
(function ($) {
	var table = null;
	var leadCache = {};

	function esc(s) {
		return $('<div/>').text(s == null ? '' : String(s)).html();
	}

	function loadLeads() {
		$('#leads_summary').text('Loading…');
		$.ajax({
			url: './php/GetAllLeads.php',
			type: 'GET',
			dataType: 'json',
			data: {
				list_id: $('#filter_list').val() || '',
				status: $('#filter_status').val() || '',
				q: $('#filter_q').val() || '',
				limit: 5000
			},
			success: function (res) {
				if (!res || !res.ok) {
					$('#leads_summary').text((res && res.message) ? res.message : 'Failed to load leads.');
					return;
				}
				var rows = res.data || [];
				leadCache = {};
				$('#leads_summary').text('Showing ' + rows.length + ' of ' + res.total + ' total leads');

				var data = rows.map(function (r) {
					leadCache[r.lead_id] = r;
					var name = [r.first_name, r.middle_initial, r.last_name].filter(Boolean).join(' ').trim();
					var listLabel = r.list_id + (r.list_name ? ' — ' + r.list_name : '');
					var actions = '<div class="lead-actions">' +
						'<button type="button" class="btn btn-xs btn-info btn-edit-lead" data-id="' + esc(r.lead_id) + '"><i class="fa fa-pencil"></i> Edit</button>' +
						'<button type="button" class="btn btn-xs btn-danger btn-delete-lead" data-id="' + esc(r.lead_id) + '"><i class="fa fa-trash"></i> Delete</button>' +
						'</div>';
					return [
						r.lead_id,
						listLabel,
						r.phone_number || '',
						name || '',
						r.email || '',
						r.city || '',
						r.state || '',
						r.status || '',
						r.vendor_lead_code || '',
						r.entry_date || '',
						actions
					];
				});

				if (table) {
					table.clear();
					table.rows.add(data);
					table.draw(false);
				} else {
					table = $('#table_leads').DataTable({
						data: data,
						pageLength: 25,
						order: [[0, 'desc']],
						responsive: true,
						columnDefs: [{ orderable: false, targets: -1 }]
					});
				}
			},
			error: function (xhr) {
				var msg = 'Request failed';
				if (xhr && xhr.responseText) {
					try {
						var j = JSON.parse(xhr.responseText);
						if (j && j.message) msg = j.message;
					} catch (e) {
						msg = 'HTTP ' + xhr.status + ': ' + String(xhr.responseText).substring(0, 180);
					}
				}
				$('#leads_summary').text(msg);
			}
		});
	}

	function openEditModal(leadId) {
		$.ajax({
			url: './php/ManageLead.php',
			type: 'GET',
			dataType: 'json',
			data: { action: 'get', lead_id: leadId },
			success: function (res) {
				if (!res || !res.ok || !res.lead) {
					swal('Error', (res && res.message) || 'Could not load lead.', 'error');
					return;
				}
				var L = res.lead;
				$('#edit_lead_id').val(L.lead_id);
				$('#edit_lead_id_label').text('#' + L.lead_id);
				$('#edit_list_id').val(String(L.list_id));
				$('#edit_phone_number').val(L.phone_number || '');
				$('#edit_phone_code').val(L.phone_code || '1');
				$('#edit_status').val(L.status || 'NEW');
				$('#edit_title').val(L.title || '');
				$('#edit_first_name').val(L.first_name || '');
				$('#edit_middle_initial').val(L.middle_initial || '');
				$('#edit_last_name').val(L.last_name || '');
				$('#edit_email').val(L.email || '');
				$('#edit_alt_phone').val(L.alt_phone || '');
				$('#edit_vendor_lead_code').val(L.vendor_lead_code || '');
				$('#edit_address1').val(L.address1 || '');
				$('#edit_address2').val(L.address2 || '');
				$('#edit_address3').val(L.address3 || '');
				$('#edit_city').val(L.city || '');
				$('#edit_state').val(L.state || '');
				$('#edit_province').val(L.province || '');
				$('#edit_postal_code').val(L.postal_code || '');
				$('#edit_country_code').val(L.country_code || '');
				$('#edit_gender').val(L.gender || 'U');
				$('#edit_date_of_birth').val(L.date_of_birth && L.date_of_birth !== '0000-00-00' ? L.date_of_birth : '');
				$('#edit_security_phrase').val(L.security_phrase || '');
				$('#edit_comments').val(L.comments || '');
				$('#editLeadModal').modal('show');
			},
			error: function () {
				swal('Error', 'Failed to load lead details.', 'error');
			}
		});
	}

	$(function () {
		loadLeads();
		$('#btn_reload').on('click', loadLeads);
		$('#filter_list, #filter_status').on('change', loadLeads);
		var t = null;
		$('#filter_q').on('keyup', function () {
			clearTimeout(t);
			t = setTimeout(loadLeads, 400);
		});

		$(document).on('click', '.btn-edit-lead', function () {
			openEditModal($(this).data('id'));
		});

		$(document).on('click', '.btn-delete-lead', function () {
			var id = $(this).data('id');
			swal({
				title: 'Delete lead #' + id + '?',
				text: 'This cannot be undone.',
				type: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#dd4b39',
				confirmButtonText: 'Yes, delete',
				closeOnConfirm: false
			}, function (ok) {
				if (!ok) return;
				$.ajax({
					url: './php/ManageLead.php',
					type: 'POST',
					dataType: 'json',
					data: { action: 'delete', lead_id: id },
					success: function (res) {
						if (res && res.ok) {
							swal('Deleted', res.message, 'success');
							loadLeads();
						} else {
							swal('Error', (res && res.message) || 'Delete failed.', 'error');
						}
					},
					error: function () {
						swal('Error', 'Delete request failed.', 'error');
					}
				});
			});
		});

		$('#btn_delete_all').on('click', function () {
			var listId = $('#filter_list').val() || '';
			var scope = listId
				? ('all leads in list ' + listId)
				: 'ALL leads in the entire system (every list)';
			swal({
				title: 'Delete ' + scope + '?',
				text: 'Type DELETE in the next prompt to confirm. This cannot be undone.',
				type: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#dd4b39',
				confirmButtonText: 'Continue',
				closeOnConfirm: false
			}, function (ok) {
				if (!ok) return;
				swal({
					title: 'Confirm deletion',
					text: 'Type DELETE to permanently remove ' + scope + ':',
					type: 'input',
					showCancelButton: true,
					closeOnConfirm: false,
					inputPlaceholder: 'DELETE'
				}, function (input) {
					if (input === false) return false;
					if (String(input).trim() !== 'DELETE') {
						swal.showInputError('You must type DELETE exactly.');
						return false;
					}
					$.ajax({
						url: './php/ManageLead.php',
						type: 'POST',
						dataType: 'json',
						data: { action: 'delete_all', list_id: listId, confirm: 'DELETE' },
						success: function (res) {
							if (res && res.ok) {
								swal('Done', res.message, 'success');
								loadLeads();
							} else {
								swal('Error', (res && res.message) || 'Delete all failed.', 'error');
							}
						},
						error: function () {
							swal('Error', 'Delete all request failed.', 'error');
						}
					});
				});
			});
		});

		$('#edit_lead_form').on('submit', function (e) {
			e.preventDefault();
			var $btn = $('#btn_save_lead').prop('disabled', true).text('Saving…');
			$.ajax({
				url: './php/ManageLead.php',
				type: 'POST',
				dataType: 'json',
				data: $(this).serialize() + '&action=update',
				success: function (res) {
					$btn.prop('disabled', false).text('Save Changes');
					if (res && res.ok) {
						$('#editLeadModal').modal('hide');
						swal('Saved', res.message, 'success');
						loadLeads();
					} else {
						swal('Error', (res && res.message) || 'Update failed.', 'error');
					}
				},
				error: function () {
					$btn.prop('disabled', false).text('Save Changes');
					swal('Error', 'Update request failed.', 'error');
				}
			});
		});
	});
})(jQuery);
</script>
</body>
</html>
