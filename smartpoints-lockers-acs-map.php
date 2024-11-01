<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
?>


<div class="acs-sp-wrapper" style="visibility: hidden; opacity: 0;">
    <div class="acs-sp-container">
        <div class="acs-sp-header">
			<img src="<?php echo esc_url(SMARTPOINTS_LOCKERS_ACS_PLUGIN_URL . 'icons/acs-courier-logo.png'); ?>" alt="<?php esc_attr_e('ACS Courier Logo', 'smartpoints-lockers-acs'); ?>" />
            <span>Επιλέξτε το ACS Point που σας εξυπηρετεί για να παραλάβετε την παραγγελία σας</span>
        </div>
        <div class="acs-sp-body">
            <div class="acs-sp-sidebar">
                <div class="acs-sp-search-wrapper">
                    <input id="acs-sp-postcode-input" type="text" placeholder="Αναζήτηση..." />
                    <button id="acs-sp-postcode-search-trigger">Αναζήτηση</button>
                </div>
                <div class="acs-sp-sidebar-points-list-wrapper">
                    <div class="acs-sp-sidebar-points-list"></div>
                </div>
            </div>
            <div class="acs-sp-sidebar-close-btn acs-sp-btn"></div>
            <div class="acs-sp-close-btn acs-sp-btn"></div>
            <div id="acs-sp-map" class="acs-sp-map"></div>
        </div>
        <div class="acs-sp-footer"></div>
    </div>
</div>
