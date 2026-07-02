<?php
/**
 * Dashboard view.
 *
 * @package StackPress
 *
 * @var \StackPress\Core             $core
 * @var \StackPress\Module_Registry  $registry
 * @var array                    $modules    id => Abstract_Module
 * @var array                    $categories slug => meta
 * @var string[]                 $active
 */

defined( 'ABSPATH' ) || exit;

// Variables below are local to this template (included within a method scope),
// not globals, so the global-prefix rule does not apply.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Group module instances by category.
$by_category = array();
foreach ( $modules as $id => $module ) {
	$by_category[ $module->category() ][ $id ] = $module;
}

// Sort each category so Essential/Recommended tools rise to the top.
foreach ( $by_category as $cat_slug => $cat_modules ) {
	uksort(
		$cat_modules,
		static function ( $a, $b ) use ( $registry, $modules ) {
			$wa = $registry->module_badge( $a )['weight'];
			$wb = $registry->module_badge( $b )['weight'];
			if ( $wa === $wb ) {
				return strcasecmp( $modules[ $a ]->name(), $modules[ $b ]->name() );
			}
			return $wb - $wa;
		}
	);
	$by_category[ $cat_slug ] = $cat_modules;
}

// Live totals for active modules.
$total_mem = 0;
$total_js  = 0.0;
$total_css = 0.0;
$total_db  = 0;
foreach ( $active as $active_id ) {
	if ( isset( $modules[ $active_id ] ) ) {
		$p          = $modules[ $active_id ]->performance_profile();
		$total_mem += (int) $p['php_memory_kb'];
		$total_js  += (float) $p['front_js_kb'];
		$total_css += (float) $p['front_css_kb'];
		$total_db  += (int) $p['db_queries'];
	}
}

$active_count   = count( $active );
$total_count    = $registry->count();
$disabled_count = max( 0, $total_count - $active_count );
$settings_pages = $registry->settings_pages();

// Other active plugins that handle the same areas (for conflict warnings on enable).
$detected_plugins = \StackPress\Environment::detected_plugins();

// Count modules the current server can't run (shown in their own sidebar view).
$unsupported_count = 0;
foreach ( $modules as $mid => $m ) {
	if ( ! $registry->requirements_met( $mid ) ) {
		$unsupported_count++;
	}
}
$tip_settings = get_option( 'stackpress_tip_settings', array() );
?>
<div class="stackpress-app">

	<aside class="stackpress-sidebar">
		<div class="stackpress-logo">
			<span class="stackpress-logo-name">StackPress</span>
			<span class="stackpress-logo-tag">
				<?php
				/* translators: %d: number of modules. */
				echo esc_html( sprintf( __( '%d modules · all free', 'stackpress' ), $total_count ) );
				?>
			</span>
		</div>

		<nav class="stackpress-nav">
			<button class="stackpress-nav-item is-active" data-filter="all">
				<i class="ti ti-layout-grid" aria-hidden="true"></i>
				<span><?php esc_html_e( 'All modules', 'stackpress' ); ?></span>
				<span class="stackpress-badge"><?php echo (int) $total_count; ?></span>
			</button>

			<button class="stackpress-nav-item stackpress-nav-active" data-filter="__active">
				<i class="ti ti-bolt" aria-hidden="true"></i>
				<span><?php esc_html_e( 'Active tools', 'stackpress' ); ?></span>
				<span class="stackpress-badge" id="stackpress-nav-active"><?php echo (int) $active_count; ?></span>
			</button>

			<?php foreach ( $categories as $slug => $cat ) : ?>
				<?php $cat_count = isset( $by_category[ $slug ] ) ? count( $by_category[ $slug ] ) : 0; ?>
				<div class="stackpress-nav-row">
					<button class="stackpress-nav-item" data-filter="<?php echo esc_attr( $slug ); ?>">
						<i class="ti ti-<?php echo esc_attr( $cat['icon'] ); ?>" aria-hidden="true"></i>
						<span><?php echo esc_html( $cat['label'] ); ?></span>
						<span class="stackpress-badge"><?php echo (int) $cat_count; ?></span>
					</button>
					<?php if ( $cat_count > 0 ) : ?>
						<button class="stackpress-nav-expand" data-cat="<?php echo esc_attr( $slug ); ?>" aria-label="<?php esc_attr_e( 'Expand category', 'stackpress' ); ?>">
							<i class="ti ti-chevron-down" aria-hidden="true"></i>
						</button>
					<?php endif; ?>
				</div>
				<?php if ( $cat_count > 0 ) : ?>
					<div class="stackpress-nav-sub" data-cat="<?php echo esc_attr( $slug ); ?>">
						<?php foreach ( $by_category[ $slug ] as $mid => $mod ) : ?>
							<button data-jump="<?php echo esc_attr( $mid ); ?>"><?php echo esc_html( $mod->name() ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php if ( $unsupported_count > 0 ) : ?>
				<button class="stackpress-nav-item stackpress-nav-warn" data-filter="__unavailable">
					<i class="ti ti-server" aria-hidden="true"></i>
					<span><?php esc_html_e( 'Needs server support', 'stackpress' ); ?></span>
					<span class="stackpress-badge"><?php echo (int) $unsupported_count; ?></span>
				</button>
			<?php endif; ?>

			<a class="stackpress-nav-item stackpress-nav-setup" href="<?php echo esc_url( admin_url( 'admin.php?page=stackpress-setup' ) ); ?>" data-modal-page="stackpress-setup" data-modal-title="<?php esc_attr_e( 'Recommended setup', 'stackpress' ); ?>">
				<i class="ti ti-tool" aria-hidden="true"></i>
				<span><?php esc_html_e( 'Recommended setup', 'stackpress' ); ?></span>
			</a>
			<a class="stackpress-nav-item stackpress-nav-agency" href="<?php echo esc_url( admin_url( 'admin.php?page=stackpress-agency' ) ); ?>" data-modal-page="stackpress-agency" data-modal-title="<?php esc_attr_e( 'Agency Mode', 'stackpress' ); ?>">
				<i class="ti ti-star" aria-hidden="true"></i>
				<span><?php esc_html_e( 'Agency Mode', 'stackpress' ); ?></span>
			</a>
		</nav>

		<div class="stackpress-brand">
			<div class="stackpress-brand-head">
				<?php
				// Prefer a bundled logo (PNG first, then the shipped SVG); else the "JM" mark.
				$stackpress_logo = '';
				if ( file_exists( STACKPRESS_PATH . 'assets/img/dice-codes-white.png' ) ) {
					$stackpress_logo = STACKPRESS_URL . 'assets/img/dice-codes-white.png';
				} elseif ( file_exists( STACKPRESS_PATH . 'assets/img/dice-codes-white.svg' ) ) {
					$stackpress_logo = STACKPRESS_URL . 'assets/img/dice-codes-white.svg';
				}
				?>
					<?php if ( '' !== $stackpress_logo ) : ?>
					<span class="stackpress-brand-by">
						<img class="stackpress-brand-logo" src="<?php echo esc_url( $stackpress_logo ); ?>" alt="Josey Mras" />
						<span class="stackpress-brand-tag"><?php esc_html_e( 'Builder & maintainer', 'stackpress' ); ?></span>
					</span>
				<?php else : ?>
					<span class="stackpress-brand-mark">JM</span>
					<span class="stackpress-brand-by">
						<span class="stackpress-brand-name">Josey Mras</span>
						<span class="stackpress-brand-tag"><?php esc_html_e( 'Builder & maintainer', 'stackpress' ); ?></span>
					</span>
				<?php endif; ?>
			</div>
			<div class="stackpress-brand-links">
				<a href="https://github.com/IamRamgarhia/StackPress-free-all-in-one-WordPress-plugin" target="_blank" rel="noopener"><i class="ti ti-info-circle" aria-hidden="true"></i> <?php esc_html_e( 'Docs & guides', 'stackpress' ); ?></a>
				<a href="mailto:joseymras88@gmail.com?subject=<?php echo rawurlencode( 'StackPress support request' ); ?>"><i class="ti ti-mail" aria-hidden="true"></i> <?php esc_html_e( 'Get support', 'stackpress' ); ?></a>
				<a href="mailto:joseymras88@gmail.com?subject=<?php echo rawurlencode( 'StackPress feature request' ); ?>"><i class="ti ti-speakerphone" aria-hidden="true"></i> <?php esc_html_e( 'Request a feature', 'stackpress' ); ?></a>
				<a href="https://buymeacoffee.com/joseymras88" target="_blank" rel="noopener"><i class="ti ti-coffee" aria-hidden="true"></i> <?php esc_html_e( 'Buy me a coffee', 'stackpress' ); ?></a>
			</div>
			<p class="stackpress-footer-credit">Built by Josey Mras<br /><a href="mailto:joseymras88@gmail.com">joseymras88@gmail.com</a></p>
		</div>
	</aside>

	<main class="stackpress-main">

		<header class="stackpress-topbar">
			<h1 class="stackpress-topbar-title" id="stackpress-current-category"><?php esc_html_e( 'All modules', 'stackpress' ); ?></h1>
			<div class="stackpress-stats">
				<span class="stackpress-stat-pill"><span class="stackpress-dot stackpress-dot-green"></span><span id="stackpress-stat-active"><?php echo (int) $active_count; ?></span>&nbsp;<?php esc_html_e( 'active', 'stackpress' ); ?></span>
				<span class="stackpress-stat-pill"><span class="stackpress-dot stackpress-dot-blue"></span><span id="stackpress-stat-mem"><?php echo esc_html( number_format_i18n( $total_mem ) ); ?></span>&nbsp;<?php esc_html_e( 'KB RAM', 'stackpress' ); ?></span>
				<span class="stackpress-stat-pill"><span class="stackpress-dot stackpress-dot-amber"></span><span id="stackpress-stat-js"><?php echo esc_html( number_format_i18n( $total_js, 0 ) ); ?></span>&nbsp;<?php esc_html_e( 'KB front-end JS', 'stackpress' ); ?></span>
			</div>
		</header>

		<section class="stackpress-pro-shell" aria-label="StackPress Pro roadmap">
			<div class="stackpress-pro-intro">
				<div>
					<h2><?php esc_html_e( 'StackPress Pro', 'stackpress' ); ?></h2>
					<p><?php esc_html_e( 'Support the next wave of premium StackPress capabilities and help fund the roadmap.', 'stackpress' ); ?></p>
				</div>
				<button type="button" class="stackpress-action-btn is-primary" id="stackpress-open-tip"><?php esc_html_e( 'Support the Pro roadmap', 'stackpress' ); ?></button>
			</div>
			<div class="stackpress-pro-grid">
				<article class="stackpress-pro-card">
					<span class="stackpress-pro-badge">Pro</span>
					<h3><?php esc_html_e( 'AI SEO Generator', 'stackpress' ); ?></h3>
					<p><?php esc_html_e( 'Generate titles, meta descriptions, FAQs, and schema from one click.', 'stackpress' ); ?></p>
					<button type="button" class="stackpress-link-btn stackpress-pro-cta" data-pro-feature="ai-seo-generator"><?php esc_html_e( 'Support this idea', 'stackpress' ); ?></button>
				</article>
				<article class="stackpress-pro-card">
					<span class="stackpress-pro-badge">Recurring revenue</span>
					<h3><?php esc_html_e( 'Cloud Backup', 'stackpress' ); ?></h3>
					<p><?php esc_html_e( 'Connect WordPress sites to your future StackPress cloud platform.', 'stackpress' ); ?></p>
					<button type="button" class="stackpress-link-btn stackpress-pro-cta" data-pro-feature="cloud-backup"><?php esc_html_e( 'Support this idea', 'stackpress' ); ?></button>
				</article>
				<article class="stackpress-pro-card">
					<span class="stackpress-pro-badge">Differentiator</span>
					<h3><?php esc_html_e( 'Site Health Score', 'stackpress' ); ?></h3>
					<p><?php esc_html_e( 'Show SEO, performance, security, and accessibility in a single score.', 'stackpress' ); ?></p>
					<button type="button" class="stackpress-link-btn stackpress-pro-cta" data-pro-feature="site-health-score"><?php esc_html_e( 'Support this idea', 'stackpress' ); ?></button>
				</article>
				<article class="stackpress-pro-card">
					<span class="stackpress-pro-badge">High value</span>
					<h3><?php esc_html_e( 'Agency Dashboard', 'stackpress' ); ?></h3>
					<p><?php esc_html_e( 'Manage multiple client websites from one interface.', 'stackpress' ); ?></p>
					<button type="button" class="stackpress-link-btn stackpress-pro-cta" data-pro-feature="agency-dashboard"><?php esc_html_e( 'Support this idea', 'stackpress' ); ?></button>
				</article>
			</div>
		</section>

		<div class="stackpress-toolbar">
			<div class="stackpress-search">
				<i class="ti ti-search" aria-hidden="true"></i>
				<input type="search" id="stackpress-search" placeholder="<?php esc_attr_e( 'Search modules…', 'stackpress' ); ?>" />
			</div>
			<div class="stackpress-filters">
				<button class="stackpress-filter-btn is-active" data-status="all"><?php esc_html_e( 'All', 'stackpress' ); ?> <span class="stackpress-fcount" id="stackpress-fc-all"><?php echo (int) $total_count; ?></span></button>
				<button class="stackpress-filter-btn" data-status="enabled"><?php esc_html_e( 'Enabled', 'stackpress' ); ?> <span class="stackpress-fcount" id="stackpress-fc-enabled"><?php echo (int) $active_count; ?></span></button>
				<button class="stackpress-filter-btn" data-status="disabled"><?php esc_html_e( 'Disabled', 'stackpress' ); ?> <span class="stackpress-fcount" id="stackpress-fc-disabled"><?php echo (int) $disabled_count; ?></span></button>
			</div>
			<div class="stackpress-actions">
				<button class="stackpress-action-btn" id="stackpress-enable-all" title="<?php esc_attr_e( 'Enable all modules shown', 'stackpress' ); ?>"><i class="ti ti-toggle-right" aria-hidden="true"></i> <?php esc_html_e( 'Enable all', 'stackpress' ); ?></button>
				<button class="stackpress-action-btn" id="stackpress-disable-all" title="<?php esc_attr_e( 'Disable all modules shown', 'stackpress' ); ?>"><i class="ti ti-power" aria-hidden="true"></i> <?php esc_html_e( 'Disable all', 'stackpress' ); ?></button>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<?php wp_nonce_field( 'stackpress_clear_cache' ); ?>
					<input type="hidden" name="action" value="stackpress_clear_cache" />
					<button type="submit" class="stackpress-action-btn"><i class="ti ti-refresh" aria-hidden="true"></i> <?php esc_html_e( 'Clear cache', 'stackpress' ); ?></button>
				</form>
			</div>
		</div>

		<div class="stackpress-grid" id="stackpress-grid">
			<?php
			foreach ( $categories as $slug => $cat ) :
				if ( empty( $by_category[ $slug ] ) ) {
					continue;
				}
				foreach ( $by_category[ $slug ] as $id => $module ) :
					$is_active   = in_array( $id, $active, true );
					$profile     = $module->performance_profile();
					$ext         = $module->external_service();
					$dep_ok      = $registry->dependencies_met( $id );
					$dep_missing = $registry->unmet_dependencies( $id );
					$req_missing = $registry->unmet_requirements( $id );
					$req_ok      = empty( $req_missing );
					$feature     = \StackPress\Environment::module_feature( $id );
					$conflict    = ( '' !== $feature && isset( $detected_plugins[ $feature ] ) ) ? $detected_plugins[ $feature ] : '';
					?>
					<div class="stackpress-card<?php echo $is_active ? ' is-enabled' : ''; ?>"
						data-module="<?php echo esc_attr( $id ); ?>"
						data-category="<?php echo esc_attr( $slug ); ?>"
						data-status="<?php echo $is_active ? 'enabled' : 'disabled'; ?>"
						data-supported="<?php echo $req_ok ? '1' : '0'; ?>"
						data-haspage="<?php echo isset( $settings_pages[ $id ] ) ? '1' : '0'; ?>"
						data-conflict="<?php echo esc_attr( $conflict ); ?>"
						data-name="<?php echo esc_attr( strtolower( $module->name() . ' ' . $module->description() ) ); ?>"
						data-mem="<?php echo (int) $profile['php_memory_kb']; ?>"
						data-js="<?php echo esc_attr( (float) $profile['front_js_kb'] ); ?>">

						<div class="stackpress-card-head">
							<span class="stackpress-icon stackpress-icon-<?php echo esc_attr( $cat['color'] ); ?>">
								<i class="ti ti-<?php echo esc_attr( $module->icon() ); ?>" aria-hidden="true"></i>
							</span>
							<div class="stackpress-card-meta">
								<span class="stackpress-card-title">
									<?php echo esc_html( $module->name() ); ?>
									<?php $badge = $registry->module_badge( $id ); ?>
									<?php if ( '' !== $badge['label'] ) : ?>
										<span class="stackpress-tag stackpress-tag-<?php echo esc_attr( $badge['key'] ); ?>"><?php echo esc_html( $badge['label'] ); ?></span>
									<?php endif; ?>
								</span>
								<span class="stackpress-card-desc"><?php echo esc_html( $module->description() ); ?></span>
							</div>
							<?php if ( $dep_ok && $req_ok ) : ?>
								<label class="stackpress-toggle-wrap" title="<?php esc_attr_e( 'Enable or disable this module', 'stackpress' ); ?>">
									<input type="checkbox" class="stackpress-toggle-input" <?php checked( $is_active ); ?> />
									<span class="stackpress-toggle"></span>
								</label>
							<?php elseif ( ! $dep_ok ) : ?>
								<span class="stackpress-dep-missing" title="<?php esc_attr_e( 'Requires WooCommerce', 'stackpress' ); ?>"><i class="ti ti-plug-off" aria-hidden="true"></i></span>
							<?php else : ?>
								<span class="stackpress-dep-missing" title="<?php esc_attr_e( 'Not supported by this server', 'stackpress' ); ?>"><i class="ti ti-server" aria-hidden="true"></i></span>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $dep_missing ) ) : ?>
							<?php foreach ( $dep_missing as $dm ) : ?>
								<div class="stackpress-req-note stackpress-dep-note">
									<i class="ti ti-plug-off" aria-hidden="true"></i>
									<span>
										<strong><?php echo esc_html( sprintf( /* translators: %s: plugin name (WooCommerce). */ __( '%s is not installed', 'stackpress' ), $dm['label'] ) ); ?></strong>
										— <?php echo esc_html( sprintf( /* translators: %s: plugin name. */ __( 'this tool turns on automatically once %s is active.', 'stackpress' ), $dm['label'] ) ); ?>
										<a href="<?php echo esc_url( admin_url( $dm['install'] ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: plugin name. */ __( 'Install %s', 'stackpress' ), $dm['label'] ) ); ?> &rarr;</a>
									</span>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php if ( ! $req_ok ) : ?>
							<?php foreach ( $req_missing as $rm ) : ?>
								<div class="stackpress-req-note">
									<i class="ti ti-server" aria-hidden="true"></i>
									<span><strong><?php echo esc_html( sprintf( /* translators: %s: capability. */ __( 'Needs %s', 'stackpress' ), $rm['label'] ) ); ?></strong> — <?php echo esc_html( $rm['hint'] ); ?></span>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php if ( $module->replaces() ) : ?>
							<div class="stackpress-replaces">
								<i class="ti ti-arrow-back-up" aria-hidden="true"></i>
								<?php
								/* translators: %s: premium plugin name. */
								echo esc_html( sprintf( __( 'Replaces %s', 'stackpress' ), $module->replaces() ) );
								?>
							</div>
						<?php endif; ?>

						<div class="stackpress-perf">
							<span class="stackpress-chip"><i class="ti ti-cpu" aria-hidden="true"></i><?php echo esc_html( $is_active ? number_format_i18n( $profile['php_memory_kb'] ) . ' KB' : '0 KB' ); ?></span>
							<span class="stackpress-chip"><i class="ti ti-code" aria-hidden="true"></i><?php echo esc_html( $is_active ? number_format_i18n( $profile['front_js_kb'], 0 ) . ' KB JS' : '0 KB JS' ); ?></span>
							<span class="stackpress-chip"><i class="ti ti-database" aria-hidden="true"></i><?php echo esc_html( $is_active ? '+' . (int) $profile['db_queries'] : '+0' ); ?></span>
							<?php if ( ! $is_active && (int) $profile['php_memory_kb'] > 0 && $dep_ok && $req_ok ) : ?>
								<span class="stackpress-chip stackpress-chip-info"><i class="ti ti-info-circle" aria-hidden="true"></i>
									<?php
									/* translators: %s: memory in KB. */
									echo esc_html( sprintf( __( 'If on: +%s KB', 'stackpress' ), number_format_i18n( $profile['php_memory_kb'] ) ) );
									?>
								</span>
							<?php endif; ?>
						</div>

						<?php if ( $ext ) : ?>
							<div class="stackpress-ext-note">
								<i class="ti ti-world" aria-hidden="true"></i>
								<?php
								/* translators: %s: external service name. */
								echo esc_html( sprintf( __( 'Uses %s (consent required on enable)', 'stackpress' ), $ext['service'] ) );
								?>
							</div>
						<?php endif; ?>

						<div class="stackpress-card-foot">
							<?php $spage = isset( $settings_pages[ $id ] ) ? $settings_pages[ $id ] : ''; ?>
							<?php if ( $spage && ! $is_active ) : ?>
								<button class="stackpress-settings-toggle is-muted" type="button" disabled title="<?php esc_attr_e( 'Enable this tool first to configure it', 'stackpress' ); ?>">
									<i class="ti ti-settings" aria-hidden="true"></i> <?php esc_html_e( 'Settings', 'stackpress' ); ?>
								</button>
							<?php else : ?>
								<button class="stackpress-settings-toggle" type="button"<?php echo $spage ? ' data-page="' . esc_attr( $spage ) . '"' : ''; ?>>
									<i class="ti ti-settings" aria-hidden="true"></i> <?php esc_html_e( 'Settings', 'stackpress' ); ?>
								</button>
							<?php endif; ?>
							<a class="stackpress-help" href="<?php echo esc_url( 'https://dicecodes.com/stackpress/docs/#mod-' . $id ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'How to use this tool', 'stackpress' ); ?>">
								<i class="ti ti-info-circle" aria-hidden="true"></i> <?php esc_html_e( 'Help', 'stackpress' ); ?>
							</a>
							<?php if ( ! $dep_ok ) : ?>
								<span class="stackpress-status-badge is-locked">
									<?php echo esc_html( ! empty( $dep_missing ) ? sprintf( /* translators: %s: plugin name. */ __( 'Needs %s', 'stackpress' ), $dep_missing[0]['label'] ) : __( 'Unavailable', 'stackpress' ) ); ?>
								</span>
							<?php elseif ( ! $req_ok ) : ?>
								<span class="stackpress-status-badge is-locked"><?php esc_html_e( 'Unavailable', 'stackpress' ); ?></span>
							<?php else : ?>
								<span class="stackpress-status-badge <?php echo $is_active ? 'is-on' : 'is-off'; ?>">
									<?php echo $is_active ? esc_html__( 'Enabled', 'stackpress' ) : esc_html__( 'Disabled', 'stackpress' ); ?>
								</span>
							<?php endif; ?>
						</div>
					</div>
					<?php
				endforeach;
			endforeach;
			?>
			<div class="stackpress-empty" id="stackpress-empty" hidden></div>
		</div>

		<section class="stackpress-support-footer" aria-label="Support StackPress">
			<div class="stackpress-support-copy">
				<h2><?php esc_html_e( 'Support StackPress', 'stackpress' ); ?></h2>
				<p><?php esc_html_e( 'If this plugin saves you time or money, tip the builder directly from this dashboard without leaving the page.', 'stackpress' ); ?></p>
			</div>
			<div class="stackpress-support-actions">
				<button type="button" class="stackpress-action-btn is-primary" id="stackpress-open-tip"><?php esc_html_e( 'Tip with Paystack', 'stackpress' ); ?></button>
				<a class="stackpress-link-btn" href="https://buymeacoffee.com/joseymras88" target="_blank" rel="noopener"><?php esc_html_e( 'Buy me a coffee', 'stackpress' ); ?></a>
			</div>
			<form method="post" class="stackpress-tip-settings-form" style="margin-top: 12px;">
				<?php wp_nonce_field( 'stackpress_tip_settings', 'stackpress_tip_settings_nonce' ); ?>
				<label class="stackpress-tip-field">
					<span><?php esc_html_e( 'Paystack public key', 'stackpress' ); ?></span>
					<input type="text" name="stackpress_tip_public_key" value="<?php echo esc_attr( isset( $tip_settings['public_key'] ) ? $tip_settings['public_key'] : '' ); ?>" />
				</label>
				<label class="stackpress-tip-field">
					<span><?php esc_html_e( 'Paystack secret key', 'stackpress' ); ?></span>
					<input type="password" name="stackpress_tip_secret_key" value="<?php echo esc_attr( isset( $tip_settings['secret_key'] ) ? $tip_settings['secret_key'] : '' ); ?>" />
				</label>
				<button type="submit" class="stackpress-action-btn"><?php esc_html_e( 'Save tip settings', 'stackpress' ); ?></button>
			</form>
		</section>
	</main>

	<div id="stackpress-modal" class="stackpress-modal" hidden>
		<div class="stackpress-modal-backdrop"></div>
		<div class="stackpress-modal-box" role="dialog" aria-modal="true" aria-labelledby="stackpress-modal-title">
			<div class="stackpress-modal-head">
				<span class="stackpress-modal-icon"><i class="ti ti-settings" aria-hidden="true"></i></span>
				<span class="stackpress-modal-titles">
					<span class="stackpress-modal-title" id="stackpress-modal-title"></span>
					<span class="stackpress-modal-sub"></span>
				</span>
				<button class="stackpress-modal-close" aria-label="<?php esc_attr_e( 'Close', 'stackpress' ); ?>">&times;</button>
			</div>
			<div class="stackpress-modal-body"></div>
		</div>
	</div>
</div>
