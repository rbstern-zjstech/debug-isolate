<?php
/**
 * Plugin Name: Debug Isolate
 * Description: Conditionally enables debugging output using PHP display_errors, based on current user or IP address.
 * Version: 0.1
 * Author: Rich Stern
 */

if (!session_id()) {
	session_start();
}

add_action('init', function () {
	$enabled = get_option('debug_isolate_enabled');
	$allowed_ips   = (array) get_option('debug_isolate_ips');
	$allowed_users = (array) get_option('debug_isolate_users');
	
	$user_id = get_current_user_id();
	$ip      = $_SERVER['REMOTE_ADDR'] ?? '';

	$should_debug = in_array($ip, $allowed_ips) || in_array($user_id, $allowed_users);

	if ($enabled && $should_debug) {
		error_reporting(E_ALL);
		ini_set('display_errors', 1);
		
		// log the debugging status:
		error_log("Debug Isolate enabled for user {$user_id} / IP {$ip}");
	} else {
		error_reporting(0);
		ini_set('display_errors', 0);
	}
}, 0);

add_action('admin_enqueue_scripts', function ($hook) {
	// Only load CSS on our settings page
	if ($hook !== 'settings_page_debug-isolate') {
		return;
	}

	wp_enqueue_style(
		'debug-isolate-admin',
		plugin_dir_url(__FILE__) . 'assets/admin.css',
		[],
		'1.0'
	);
});

add_action('admin_menu', function () {
	add_options_page(
		'Debug Isolate Settings',
		'Debug Isolate',
		'manage_options',
		'debug-isolate',
		'debug_isolate_render_settings_page'
	);
});

add_action('admin_notices', function () {
	if (!empty($_SESSION['debug_isolate_invalid_ips'])) {
		$invalid = implode(', ', array_map('esc_html', $_SESSION['debug_isolate_invalid_ips']));
		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p><strong>Debug Isolate:</strong> The following IPs were not saved because they are invalid: ' . $invalid . '</p>';
		echo '</div>';
		unset($_SESSION['debug_isolate_invalid_ips']);
	}
});

add_action('admin_bar_menu', function ($wp_admin_bar) {
	// Only for logged-in users viewing the admin bar
	if (!is_user_logged_in() || !is_admin_bar_showing()) return;

	$enabled       = get_option('debug_isolate_enabled');
	$allowed_ips   = (array) get_option('debug_isolate_ips', []);
	$allowed_users = (array) get_option('debug_isolate_users', []);

	$user_id = get_current_user_id();
	$ip      = $_SERVER['REMOTE_ADDR'] ?? '';

	$should_debug = $enabled && (in_array($ip, $allowed_ips) || in_array($user_id, $allowed_users));

	if (!$should_debug) return;

	$wp_admin_bar->add_node([
		'id'    => 'debug-isolate-indicator',
		'title' => '<span style="display: inline-flex; align-items: center; gap: 6px;">
						<span style="color: #f00; font-size: 16px; line-height: 1;" class="no-emoji">&#9888;&#xfe0e;</span>
						<span class="ab-label">Debug Isolate Active</span>
					</span>',
		'href'  => admin_url('options-general.php?page=debug-isolate'),
		'meta'  => [
			'title' => 'Debug Isolate is active for you',
		],
	]);
}, 100);

add_filter('pre_update_option_debug_isolate_ips', function ($value) {
	if (!is_array($value)) return [];

	$valid_ips   = [];
	$invalid_ips = [];

	foreach (array_map('trim', $value) as $ip) {
		if (filter_var($ip, FILTER_VALIDATE_IP)) {
			$valid_ips[] = $ip;
		} elseif ($ip !== '') {
			$invalid_ips[] = $ip;
		}
	}

	if (!empty($invalid_ips)) {
		$_SESSION['debug_isolate_invalid_ips'] = $invalid_ips;
	}

	return $valid_ips;
});

// Sanitize and clean user list before saving
add_filter('pre_update_option_debug_isolate_users', function ($value) {
	if (!is_array($value)) return [];

	return array_filter($value, function ($user_id) {
		return $user_id !== '';
	});
});

function debug_isolate_render_settings_page() {
	$enabled = get_option('debug_isolate_enabled', false);
	$ips     = (array) get_option('debug_isolate_ips', []);
	$users   = (array) get_option('debug_isolate_users', []);

	// Get user list for dropdown
	$wp_users = get_users(['fields' => ['ID', 'display_name']]);

	?>
	<div class="wrap">
		<h1>Debug Isolate Settings</h1>
		
		<p class='plugin-use-narrative'><strong>Debug Isolate</strong> allows WordPress debug output to be selectively enabled for specific users or IP addresses, without exposing sensitive or distracting error messages to public visitors. Use the controls below to manage who receives debug output in a production-safe manner.</p>
		
		<form method="post" action="options.php">
			<?php settings_fields('debug_isolate'); ?>
			<?php do_settings_sections('debug-isolate'); ?>

			<table class="form-table">
				<tr>
					<th scope="row">Enable Debug Isolate</th>
					<td>
						<input type="checkbox" name="debug_isolate_enabled" value="1" <?= checked($enabled, true, false) ?>>
					</td>
				</tr>

				<tr>
					<th scope="row">Allowed IPs</th>
					<td>
						<div id="debug-ip-list">
							<?php foreach ($ips as $ip): ?>
								<div class="debug-ip-row">
									<input type="text" name="debug_isolate_ips[]" value="<?= esc_attr($ip) ?>">
									<button type="button" class="button remove-ip">Delete</button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button" id="add-ip">Add IP</button>
					</td>
				</tr>

				<tr>
					<th scope="row">Allowed Users</th>
					<td>
						<div id="debug-user-list">
							<?php foreach ($users as $user_id): ?>
								<div class="debug-user-row">
									<select name="debug_isolate_users[]">
										<option value="" <?= selected('', $user_id, false) ?>>&mdash; Select a user &mdash;</option>
										<?php foreach ($wp_users as $user): ?>
											<option value="<?= $user->ID ?>" <?= selected($user->ID, $user_id, false) ?>>
												<?= esc_html($user->display_name) ?>
											</option>
										<?php endforeach; ?>
									</select>
									<button type="button" class="button remove-user">Delete</button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button" id="add-user">Add User</button>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		const ipList = document.getElementById('debug-ip-list');
		const userList = document.getElementById('debug-user-list');
		const addIpBtn = document.getElementById('add-ip');
		const addUserBtn = document.getElementById('add-user');

		const users = <?= json_encode(array_map(fn($u) => ['id' => $u->ID, 'name' => $u->display_name], $wp_users)) ?>;

		// --- IP HANDLERS ---
		addIpBtn.addEventListener('click', function () {
			const row = document.createElement('div');
			row.className = 'debug-ip-row';
			row.innerHTML = `
				<input type="text" name="debug_isolate_ips[]" />
				<button type="button" class="button remove-ip">Delete</button>
			`;
			ipList.appendChild(row);
		});

		ipList.addEventListener('click', function (e) {
			if (e.target.classList.contains('remove-ip')) {
				e.target.closest('.debug-ip-row').remove();
			}
		});

		// --- USER HANDLERS ---
		function createUserSelect(selectedValue = '') {
			const select = document.createElement('select');
			select.name = 'debug_isolate_users[]';

			const defaultOpt = document.createElement('option');
			defaultOpt.value = '';
			defaultOpt.innerHTML = '&mdash; Select a user &mdash;';
			select.appendChild(defaultOpt);

			users.forEach(user => {
				const opt = document.createElement('option');
				opt.value = user.id;
				opt.textContent = user.name;
				if (String(user.id) === String(selectedValue)) {
					opt.selected = true;
				}
				select.appendChild(opt);
			});

			return select;
		}

		function refreshUserDeleteButtons() {
			const rows = userList.querySelectorAll('.debug-user-row');

			rows.forEach(row => {
				// Remove any existing delete button
				const old = row.querySelector('.remove-user');
				if (old) old.remove();

				const select = row.querySelector('select');
				const value = select?.value;

				// Show delete if the row has a selected user
				if (value) {
					const del = document.createElement('button');
					del.type = 'button';
					del.className = 'button remove-user';
					del.textContent = 'Delete';
					row.appendChild(del);
				}
			});
		}

		addUserBtn.addEventListener('click', function () {
			const lastSelect = userList.querySelector('.debug-user-row:last-child select');
			if (lastSelect && !lastSelect.value) {
				alert("Please select a user before adding another.");
				return;
			}

			const row = document.createElement('div');
			row.className = 'debug-user-row';
			row.appendChild(createUserSelect());
			userList.appendChild(row);
			refreshUserDeleteButtons();
		});

		userList.addEventListener('click', function (e) {
			if (e.target.classList.contains('remove-user')) {
				e.target.closest('.debug-user-row').remove();
				refreshUserDeleteButtons();
			}
		});

		userList.addEventListener('change', function (e) {
			if (e.target.tagName === 'SELECT') {
				refreshUserDeleteButtons();
			}
		});

		refreshUserDeleteButtons(); // Initial cleanup
	});
	</script>
	<?php
}

add_action('admin_init', function () {
	register_setting('debug_isolate', 'debug_isolate_enabled');
	register_setting('debug_isolate', 'debug_isolate_ips');
	register_setting('debug_isolate', 'debug_isolate_users');
});
