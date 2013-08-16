<?php
/**
 * Exchange will build your add-on's settings page for you and link to it from our add-on
 * screen. You are free to link from it elsewhere as well if you'd like... or to not use our API
 * at all. This file has all the functions related to registering the page, printing the form, and saving
 * the options. This includes the wizard settings. Additionally, we use the Exchange storage API to
 * save / retreive options. Add-ons are not required to do this.
*/

/**
 * This is the function registered in the options array when it_exchange_register_addon was called for 2checkout
 *
 * It tells Exchange where to find the settings page
 *
 * @return void
*/
function it_exchange_2checkout_addon_settings_callback() {
    $IT_Exchange_2checkout_Add_On = new IT_Exchange_2checkout_Add_On();
    $IT_Exchange_2checkout_Add_On->print_settings_page();
}

/**
 * Outputs wizard settings for 2Checkout
 *
 * Exchange allows add-ons to add a small amount of settings to the wizard.
 * You can add these settings to the wizard by hooking into the following action:
 * - it_exchange_print_[addon-slug]_wizard_settings
 * Exchange exspects you to print your fields here.
 *
 * @since 1.0.0
 * @todo make this better, probably
 * @param object $form Current IT Form object
 * @return void
*/
function it_exchange_print_2checkout_wizard_settings( $form ) {
    $IT_Exchange_2checkout_Add_On = new IT_Exchange_2checkout_Add_On();
    $settings = it_exchange_get_option( 'addon_2checkout', true );
    $form_values = ITUtility::merge_defaults( ITForm::get_post_data(), $settings );
    $hide_if_js =  it_exchange_is_addon_enabled( '2checkout' ) ? '' : 'hide-if-js';
    ?>
    <div class="field 2checkout-wizard <?php echo $hide_if_js; ?>">
    <?php if ( empty( $hide_if_js ) ) { ?>
        <input class="enable-2checkout" type="hidden" name="it-exchange-transaction-methods[]" value="2checkout" />
    <?php } ?>
    <?php $IT_Exchange_2checkout_Add_On->get_2checkout_payment_form_table( $form, $form_values ); ?>
    </div>
    <?php
}
add_action( 'it_exchange_print_2checkout_wizard_settings', 'it_exchange_print_2checkout_wizard_settings' );

/**
 * Saves 2checkout settings when the Wizard is saved
 *
 * @since 1.0.0
 *
 * @return void
*/
function it_exchange_save_2checkout_wizard_settings( $errors ) {
    if ( ! empty( $errors ) )
        return $errors;

    $IT_Exchange_2checkout_Add_On = new IT_Exchange_2checkout_Add_On();
    return $IT_Exchange_2checkout_Add_On->twocheckout_save_wizard_settings();
}
// add_action( 'it_exchange_save_2checkout_wizard_settings', 'it_exchange_save_2checkout_wizard_settings' );

/**
 * Default settings for 2checkout
 *
 * @since 1.0.0
 *
 * @param array $values
 * @return array
*/
function it_exchange_2checkout_addon_default_settings( $values ) {
    $defaults = array(
        '2checkout-test-mode'             => false,
        '2checkout-sid'                   => '',
        '2checkout-secret'                => '',
        '2checkout-language'              => 'en',
        '2checkout-purchase-button-label' => __( 'Purchase', 'it-l10n-exchange-addon-2checkout' ),
    );
    $values = ITUtility::merge_defaults( $values, $defaults );
    return $values;
}
add_filter( 'it_storage_get_defaults_exchange_addon_2checkout', 'it_exchange_2checkout_addon_default_settings' );

/**
 * Class for 2Checkout
 * @since 1.0.0
*/
class IT_Exchange_2checkout_Add_On {

    /**
     * @var boolean $_is_admin true or false
     * @since 1.0.0
    */
    var $_is_admin;

    /**
     * @var string $_current_page Current $_GET['page'] value
     * @since 1.0.0
    */
    var $_current_page;

    /**
     * @var string $_current_add_on Current $_GET['add-on-settings'] value
     * @since 1.0.0
    */
    var $_current_add_on;

    /**
     * @var string $status_message will be displayed if not empty
     * @since 1.0.0
    */
    var $status_message;

    /**
     * @var string $error_message will be displayed if not empty
     * @since 1.0.0
    */
    var $error_message;

    /**
     * Set up the class
     *
     * @since 1.0.0
    */
    function __construct() {
        $this->_is_admin       = is_admin();
        $this->_current_page   = empty( $_GET['page'] ) ? false : $_GET['page'];
        $this->_current_add_on = empty( $_GET['add-on-settings'] ) ? false : $_GET['add-on-settings'];

        if ( ! empty( $_POST ) && $this->_is_admin && 'it-exchange-addons' == $this->_current_page && '2checkout' == $this->_current_add_on ) {
            add_action( 'it_exchange_save_add_on_settings_2checkout', array( $this, 'save_settings' ) );
            do_action( 'it_exchange_save_add_on_settings_2checkout' );
        }
    }

    /**
     * Prints settings page
     *
     * @since 1.0.0
    */
    function print_settings_page() {
        $settings = it_exchange_get_option( 'addon_2checkout', true );
        $form_values  = empty( $this->error_message ) ? $settings : ITForm::get_post_data();
        $form_options = array(
            'id'      => apply_filters( 'it_exchange_add_on_2checkout', 'it-exchange-add-on-2checkout-settings' ),
            'enctype' => apply_filters( 'it_exchange_add_on_2checkout_settings_form_enctype', false ),
            'action'  => 'admin.php?page=it-exchange-addons&add-on-settings=2checkout',
        );
        $form         = new ITForm( $form_values, array( 'prefix' => 'it-exchange-add-on-2checkout' ) );

        if ( ! empty ( $this->status_message ) )
            ITUtility::show_status_message( $this->status_message );
        if ( ! empty( $this->error_message ) )
            ITUtility::show_error_message( $this->error_message );

        ?>
        <div class="wrap">
            <?php screen_icon( 'it-exchange' ); ?>
            <h2><?php _e( '2Checkout Settings', 'it-l10n-exchange-addon-2checkout' ); ?></h2>

            <?php do_action( 'it_exchange_2checkout_settings_page_top' ); ?>
            <?php do_action( 'it_exchange_addon_settings_page_top' ); ?>
            <?php $form->start_form( $form_options, 'it-exchange-2checkout-settings' ); ?>
                <?php do_action( 'it_exchange_2checkout_settings_form_top' ); ?>
                <?php $this->get_2checkout_payment_form_table( $form, $form_values ); ?>
                <?php do_action( 'it_exchange_2checkout_settings_form_bottom' ); ?>
                <p class="submit">
                    <?php $form->add_submit( 'submit', array( 'value' => __( 'Save Changes', 'it-l10n-exchange-addon-2checkout' ), 'class' => 'button button-primary button-large' ) ); ?>
                </p>
            <?php $form->end_form(); ?>
            <?php do_action( 'it_exchange_2checkout_settings_page_bottom' ); ?>
            <?php do_action( 'it_exchange_addon_settings_page_bottom' ); ?>
        </div>
        <?php
    }

    /**
     * Builds Settings Form Table
     *
     * @since 1.0.0
     */
    function get_2checkout_payment_form_table( $form, $settings = array() ) {

        $general_settings = it_exchange_get_option( 'settings_general' );

        if ( !empty( $settings ) )
            foreach ( $settings as $key => $var )
                $form->set_option( $key, $var );

        if ( ! empty( $_GET['page'] ) && 'it-exchange-setup' == $_GET['page'] ) : ?>
            <h3><?php _e( '2Checkout', 'it-l10n-exchange-addon-2checkout' ); ?></h3>
        <?php endif; ?>
        <div class="it-exchange-addon-settings it-exchange-2checkout-addon-settings">
            <p>
                <?php _e( 'To get 2Checkout set up for use with Exchange, you\'ll need to add the following information from your 2Checkout account.', 'it-l10n-exchange-addon-2checkout' ); ?>
            </p>
            <p>
                <?php _e( 'Don\'t have a 2Checkout account yet?', 'it-l10n-exchange-addon-2checkout' ); ?> <a href="http://2checkout.com" target="_blank"><?php _e( 'Go set one up here', 'it-l10n-exchange-addon-2checkout' ); ?></a>.
            </p>
            <h4><?php _e( 'Fill out your 2Checkout API Credentials', 'it-l10n-exchange-addon-2checkout' ); ?></h4>
            <p>
                <label for="2checkout-sid"><?php _e( '2Checkout Account Number (SID)', 'it-l10n-exchange-addon-2checkout' ); ?> <span class="tip" title="<?php _e( 'Your 2Checkout Account Number, or SID, is found in the top-right corner of your 2CO account dashboard.', 'it-l10n-exchange-addon-2checkout' ); ?>">i</span></label>
                <?php $form->add_text_box( '2checkout-sid' ); ?>
            </p>
            <p>
                <label for="2checkout-secret"><?php _e( '2Checkout Secret Word', 'it-l10n-exchange-addon-2checkout' ); ?> <span class="tip" title="<?php _e( 'The 2Checkout API Password is found in...', 'it-l10n-exchange-addon-2checkout' ); ?>">i</span></label>
                <?php $form->add_password( '2checkout-secret' ); ?>
            </p>

            <h4><?php _e( 'Optional: Set Default Language for 2Checkout Pages', 'it-l10n-exchange-addon-2checkout' ); ?></h4>
            <p>
                <label for="2checkout-language"><?php _e( '2Checkout Language', 'it-l10n-exchange-addon-2checkout' ); ?> <span class="tip" title="<?php _e( 'Select the language that 2Checkout should use on their checkout page.', 'it-l10n-exchange-addon-2checkout' ); ?>">i</span></label>
                <?php
                    $languages = array('en'=>'English', 'es_ib'=>'Spanish (European) — Español', 'es_la'=>'Spanish (Latin) — Español', 'fr'=>'French — Français', 'ja'=>'Japanese — 日本語', 'de'=>'German — Deutsch', 'it'=>'Italian — Italiano', 'nl'=>'Dutch — Nederlands', 'pt'=>'Portuguese — Português', 'el'=>'Greek — Ελληνική', 'sv'=>'Swedish — Svenska', 'zh'=>'Chinese (Traditional) — 語言名稱', 'sl'=>'Slovenian — Slovene', 'da'=>'Danish — Dansk', 'no'=>'Norwegian — Norsk');
                    $form->add_drop_down( '2checkout-language', $languages );
                ?>
            </p>

            <h4><?php _e( 'Optional: Edit Purchase Button Label', 'it-l10n-exchange-addon-2checkout' ); ?></h4>
            <p>
                <label for="2checkout-purchase-button-label"><?php _e( 'Purchase Button Label', 'it-l10n-exchange-addon-2checkout' ); ?> <span class="tip" title="<?php _e( 'This is the text inside the button your customers will press to purchase with 2Checkout', 'it-l10n-exchange-addon-2checkout' ); ?>">i</span></label>
                <?php $form->add_text_box( '2checkout-purchase-button-label' ); ?>
            </p>

            <h4 class="hide-if-wizard"><?php _e( 'Optional: Enable 2Checkout Test Mode', 'it-l10n-exchange-addon-2checkout' ); ?></h4>
            <p class="hide-if-wizard">
                <?php $form->add_check_box( '2checkout-test-mode', array( 'class' => 'show-test-mode-options' ) ); ?>
                <label for="2checkout-test-mode"><?php _e( 'Enable 2Checkout Test Mode?', 'it-l10n-exchange-addon-2checkout' ); ?> <span class="tip" title="<?php _e( 'Use this mode for testing your store. This mode will need to be disabled when the store is ready to process customer payments.', 'it-l10n-exchange-addon-2checkout' ); ?>">i</span></label>
            </p>
        </div>
        <?php
    }

    /**
     * Save settings
     *
     * @since 1.0.0
     * @return void
    */
    function save_settings() {
        $defaults = it_exchange_get_option( 'addon_2checkout' );
        $new_values = wp_parse_args( ITForm::get_post_data(), $defaults );

        // Check nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'it-exchange-2checkout-settings' ) ) {
            $this->error_message = __( 'Error. Please try again', 'it-l10n-exchange-addon-2checkout' );
            return;
        }

        $errors = apply_filters( 'it_exchange_add_on_2checkout_validate_settings', $this->get_form_errors( $new_values ), $new_values );
        if ( ! $errors && it_exchange_save_option( 'addon_2checkout', $new_values ) ) {
            ITUtility::show_status_message( __( 'Settings saved.', 'it-l10n-exchange-addon-2checkout' ) );
        } else if ( $errors ) {
            $errors = implode( '<br />', $errors );
            $this->error_message = $errors;
        } else {
            $this->status_message = __( 'Settings not saved.', 'it-l10n-exchange-addon-2checkout' );
        }
    }

    function twocheckout_save_wizard_settings() {
        if ( empty( $_REQUEST['it_exchange_settings-wizard-submitted'] ) )
            return;

        $twocheckout_settings = array();

        // Fields to save
        $fields = array(
            '2checkout-sid',
            '2checkout-secret',
            '2checkout-language',
            '2checkout-test-mode',
            '2checkout-purchase-button-label',
        );
        $default_wizard_2checkout_settings = apply_filters( 'default_wizard_2checkout_settings', $fields );

        foreach( $default_wizard_2checkout_settings as $var ) {
            if ( isset( $_REQUEST['it_exchange_settings-' . $var] ) ) {
                $twocheckout_settings[$var] = $_REQUEST['it_exchange_settings-' . $var];
            }
        }

        $settings = wp_parse_args( $twocheckout_settings, it_exchange_get_option( 'addon_2checkout' ) );

        if ( $error_msg = $this->get_form_errors( $settings ) ) {

            return $error_msg;

        } else {
            it_exchange_save_option( 'addon_2checkout', $settings );
            $this->status_message = __( 'Settings Saved.', 'it-l10n-exchange-addon-2checkout' );
        }

        return;
    }

    /**
     * Validates for values
     *
     * Returns string of errors if anything is invalid
     *
     * @since 1.0.0
     * @return void
    */
    public function get_form_errors( $values ) {

        $errors = array();
        if ( empty( $values['2checkout-sid'] ) )
            $errors[] = __( 'Please include your 2Checkout Account ID (SID)', 'it-l10n-exchange-addon-2checkout' );
        if ( empty( $values['2checkout-secret'] ) )
            $errors[] = __( 'Please include your 2Checkout Secret Word', 'it-l10n-exchange-addon-2checkout' );

        return $errors;
    }

}
