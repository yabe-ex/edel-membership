<?php

class EdelMembershipAdmin {
    private $option_group = 'edel_membership_options';
    private $option_name  = 'edel_membership_settings';

    function create_menu() {
        add_submenu_page(
            'options-general.php',
            EDEL_MEMBERSHIP_NAME,
            EDEL_MEMBERSHIP_NAME,
            'manage_options',
            'edel-membership',
            array($this, 'show_setting_page')
        );
    }

    function admin_init() {
        register_setting($this->option_group, $this->option_name, array($this, 'sanitize_settings'));

        // --- セクション1: デザイン・基本設定 ---
        add_settings_section('edel_design_section', 'デザイン・基本設定', null, 'edel-membership');
        add_settings_field('base_color', 'ベースカラー', array($this, 'field_base_color'), 'edel-membership', 'edel_design_section');

        add_settings_field('email_only_register', '登録フォーム設定', array($this, 'field_checkbox_input'), 'edel-membership', 'edel_design_section', array(
            'key' => 'email_only_register',
            'label' => 'メールアドレスのみで登録できるようにする（ユーザー名はメールアドレスと同じになります）'
        ));

        // --- セクション2: ページ・リダイレクト設定 ---
        add_settings_section('edel_redirect_section', 'ページ・リダイレクト設定', null, 'edel-membership');

        // ★追加: ログインページのURL
        add_settings_field('login_page_url', 'ログインページのURL', array($this, 'field_url_input'), 'edel-membership', 'edel_redirect_section', array(
            'key' => 'login_page_url',
            'desc' => 'ショートコード <code>[edel_login]</code> を設置した固定ページのURL。メールやメッセージ内のリンクで使用されます。'
        ));

        add_settings_field('reset_password_page_url', '【必須】パスワード再設定用ページのURL', array($this, 'field_url_input'), 'edel-membership', 'edel_redirect_section', array(
            'key' => 'reset_password_page_url',
            'desc' => 'ショートコード <code>[edel_password_reset]</code> ではなく、<strong>[edel_password_new]</strong> を設置したページのURLを入力してください。'
        ));

        add_settings_field('login_redirect_url', 'ログイン後のリダイレクトURL', array($this, 'field_url_input'), 'edel-membership', 'edel_redirect_section', array('key' => 'login_redirect_url'));
        add_settings_field('logout_redirect_url', 'ログアウト後のリダイレクトURL', array($this, 'field_url_input'), 'edel-membership', 'edel_redirect_section', array('key' => 'logout_redirect_url'));
        add_settings_field('register_redirect_url', '登録後のリダイレクトURL', array($this, 'field_url_input'), 'edel-membership', 'edel_redirect_section', array('key' => 'register_redirect_url'));
        add_settings_field('auto_login_after_register', '登録後の自動ログイン', array($this, 'field_checkbox_input'), 'edel-membership', 'edel_redirect_section', array('key' => 'auto_login_after_register', 'label' => '有効にする'));

        // --- セクション3: メール設定 ---
        add_settings_section('edel_mail_section', 'メール設定', array($this, 'section_mail_desc'), 'edel-membership');
        add_settings_field('mail_from_name', '送信者名', array($this, 'field_text_input'), 'edel-membership', 'edel_mail_section', array('key' => 'mail_from_name'));
        add_settings_field('mail_from_email', '送信元メールアドレス', array($this, 'field_text_input'), 'edel-membership', 'edel_mail_section', array('key' => 'mail_from_email'));

        add_settings_field('register_mail_subject', '【登録完了】件名', array($this, 'field_text_input'), 'edel-membership', 'edel_mail_section', array('key' => 'register_mail_subject'));
        add_settings_field('register_mail_body', '【登録完了】本文', array($this, 'field_textarea_input'), 'edel-membership', 'edel_mail_section', array('key' => 'register_mail_body', 'default' => "{username} 様\n\n登録ありがとうございます。"));

        add_settings_field('reset_mail_subject', '【パスワードリセット】件名', array($this, 'field_text_input'), 'edel-membership', 'edel_mail_section', array('key' => 'reset_mail_subject'));
        add_settings_field('reset_mail_body', '【パスワードリセット】本文', array($this, 'field_textarea_input'), 'edel-membership', 'edel_mail_section', array('key' => 'reset_mail_body', 'default' => "パスワードの再設定リクエストを受け付けました。\n以下のリンクをクリックして新しいパスワードを設定してください。\n{reset_url}"));

        // --- セクション4: reCAPTCHA v3 ---
        add_settings_section('edel_recaptcha_section', 'Google reCAPTCHA v3 設定', null, 'edel-membership');
        add_settings_field('recaptcha_enabled', 'reCAPTCHA有効化', array($this, 'field_checkbox_input'), 'edel-membership', 'edel_recaptcha_section', array('key' => 'recaptcha_enabled', 'label' => '有効にする'));
        add_settings_field('recaptcha_site_key', 'サイトキー (Site Key)', array($this, 'field_text_input'), 'edel-membership', 'edel_recaptcha_section', array('key' => 'recaptcha_site_key'));
        add_settings_field('recaptcha_secret_key', 'シークレットキー (Secret Key)', array($this, 'field_text_input'), 'edel-membership', 'edel_recaptcha_section', array('key' => 'recaptcha_secret_key'));
    }

    // --- 汎用フィールド出力関数 ---
    function field_base_color() {
        $options = get_option($this->option_name);
        $val = isset($options['base_color']) ? esc_attr($options['base_color']) : '#333333';
        echo "<input type='color' name='{$this->option_name}[base_color]' value='$val' />";
    }

    function field_text_input($args) {
        $options = get_option($this->option_name);
        $key = $args['key'];
        $val = isset($options[$key]) ? esc_attr($options[$key]) : '';
        echo "<input type='text' name='{$this->option_name}[$key]' value='$val' class='large-text' />";
    }

    function field_url_input($args) {
        $options = get_option($this->option_name);
        $key = $args['key'];
        $val = isset($options[$key]) ? esc_attr($options[$key]) : '';
        echo "<input type='url' name='{$this->option_name}[$key]' value='$val' class='regular-text' />";
        if (isset($args['desc'])) echo "<p class='description'>{$args['desc']}</p>";
    }

    function field_textarea_input($args) {
        $options = get_option($this->option_name);
        $key = $args['key'];
        $default = isset($args['default']) ? $args['default'] : '';
        $val = isset($options[$key]) ? esc_textarea($options[$key]) : $default;
        echo "<textarea name='{$this->option_name}[$key]' rows='5' class='large-text'>$val</textarea>";
    }

    function field_checkbox_input($args) {
        $options = get_option($this->option_name);
        $key = $args['key'];
        $checked = isset($options[$key]) && $options[$key] ? 'checked' : '';
        echo "<label><input type='checkbox' name='{$this->option_name}[$key]' value='1' $checked /> {$args['label']}</label>";
    }

    function section_mail_desc() {
        echo "<p>利用可能なタグ: <code>{username}</code>, <code>{blogname}</code>, <code>{reset_url}</code> (リセットメールのみ)</p>";
    }

    function sanitize_settings($input) {
        return $input;
    }

    function admin_enqueue($hook) { /* 必要なら記述 */
    }

    function plugin_action_links($links) {
        $url = '<a href="' . esc_url(admin_url("/options-general.php?page=edel-membership")) . '">設定</a>';
        array_unshift($links, $url);
        return $links;
    }

    function show_setting_page() {
?>
        <div class="wrap">
            <h1><?php echo EDEL_MEMBERSHIP_NAME; ?> 設定</h1>
            <form method="post" action="options.php">
                <?php settings_fields($this->option_group);
                do_settings_sections('edel-membership');
                submit_button(); ?>
            </form>
        </div>
<?php
    }
}
