<?php

class EdelMembershipFront {
    private $option_name = 'edel_membership_settings';
    private $options;
    private $errors = array();

    public function __construct() {
        $this->options = get_option($this->option_name);
        if (!$this->options) {
            $this->options = array();
        }

        add_action('init', array($this, 'handle_form_submission'));
        add_action('wp_head', array($this, 'output_custom_css'));

        add_shortcode('edel_login', array($this, 'shortcode_login'));
        add_shortcode('edel_register', array($this, 'shortcode_register'));
        add_shortcode('edel_password_reset', array($this, 'shortcode_reset_request'));
        add_shortcode('edel_password_new', array($this, 'shortcode_reset_new'));
    }

    // --- CSS/JS エンキュー ---
    function front_enqueue() {
        $version = (defined('EDEL_MEMBERSHIP_DEVELOP') && true === EDEL_MEMBERSHIP_DEVELOP) ? time() : EDEL_MEMBERSHIP_VERSION;
        $strategy = array('in_footer' => true, 'strategy'  => 'defer');

        // CSS
        wp_register_style(EDEL_MEMBERSHIP_SLUG . '-front',  EDEL_MEMBERSHIP_URL . '/css/front.css', array(), $version);
        wp_enqueue_style(EDEL_MEMBERSHIP_SLUG . '-front');

        // --- 設定値の取得 ---
        $raw_enabled = isset($this->options['recaptcha_enabled']) ? $this->options['recaptcha_enabled'] : 'NOT SET';
        $raw_site_key = isset($this->options['recaptcha_site_key']) ? $this->options['recaptcha_site_key'] : 'NOT SET';

        $is_recaptcha_active = $this->is_recaptcha_active();
        $site_key = $is_recaptcha_active ? $raw_site_key : '';

        // JSへ渡す変数
        $js_vars = array(
            'recaptcha_enabled' => $is_recaptcha_active,
            'site_key'          => $site_key,
            'debug_diagnosis'   => array(
                'raw_enabled_value' => $raw_enabled,
                'raw_site_key'      => $raw_site_key,
                'is_active_result'  => $is_recaptcha_active
            )
        );

        // 変数渡し
        wp_register_script(EDEL_MEMBERSHIP_SLUG . '-front', EDEL_MEMBERSHIP_URL . '/js/front.js', array('jquery'), $version, $strategy);
        wp_localize_script(EDEL_MEMBERSHIP_SLUG . '-front', 'edel_membership_vars', $js_vars);

        if ($is_recaptcha_active) {
            // ★修正1: ハンドル名を 'edel-google-recaptcha' に変更して競合回避
            // これにより、他のプラグインが読み込んでいても、こちらのキー付きスクリプトが別途ロードされます
            wp_enqueue_script('edel-google-recaptcha', "https://www.google.com/recaptcha/api.js?render={$site_key}", array(), null, true);

            // ★修正2: 依存関係も新しいハンドル名に変更して、読み込み順序を保証
            // (一度登録解除してから依存関係を追加して再登録)
            wp_deregister_script(EDEL_MEMBERSHIP_SLUG . '-front');
            wp_register_script(EDEL_MEMBERSHIP_SLUG . '-front', EDEL_MEMBERSHIP_URL . '/js/front.js', array('jquery', 'edel-google-recaptcha'), $version, $strategy);

            // 再度localizeが必要になるためセット
            wp_localize_script(EDEL_MEMBERSHIP_SLUG . '-front', 'edel_membership_vars', $js_vars);
        }

        wp_enqueue_script(EDEL_MEMBERSHIP_SLUG . '-front');
    }

    // --- カスタムCSS変数出力 ---
    function output_custom_css() {
        $color = isset($this->options['base_color']) ? $this->options['base_color'] : '#333333';
        echo "<style>:root { --edel-base-color: {$color}; }</style>\n";
    }

    // --- POST送信ハンドラー ---
    function handle_form_submission() {
        if (!isset($_POST['edel_action'])) return;

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'edel_action_nonce')) {
            $this->errors[] = 'セキュリティチェックに失敗しました。ページを再読み込みしてください。';
            return;
        }

        // reCAPTCHAチェック
        if (!$this->verify_recaptcha()) {
            $this->errors[] = 'reCAPTCHA認証に失敗しました。';
            return;
        }

        switch ($_POST['edel_action']) {
            case 'login':
                $this->process_login();
                break;
            case 'register':
                $this->process_register();
                break;
            case 'reset_request':
                $this->process_reset_request();
                break;
            case 'reset_new':
                $this->process_reset_new();
                break;
        }
    }

    // --- ロジック: ログイン ---
    private function process_login() {
        $creds = array(
            'user_login'    => sanitize_text_field($_POST['log']),
            'user_password' => $_POST['pwd'],
            'remember'      => isset($_POST['remember']),
        );
        $user = wp_signon($creds, false);

        if (is_wp_error($user)) {
            $this->errors[] = 'ユーザー名またはパスワードが間違っています。';
        } else {
            $redirect = !empty($this->options['login_redirect_url']) ? $this->options['login_redirect_url'] : home_url();
            wp_safe_redirect($redirect);
            exit;
        }
    }

    // --- ロジック: 登録 ---
    private function process_register() {
        $is_email_only = !empty($this->options['email_only_register']);
        $email    = sanitize_email($_POST['user_email']);
        $password = $_POST['user_pass'];

        if ($is_email_only) {
            $username = $email;
        } else {
            $username = sanitize_user($_POST['user_login']);
        }

        if (empty($username) || empty($email) || empty($password)) {
            $this->errors[] = '全ての項目を入力してください。';
            return;
        }

        if (username_exists($username) || email_exists($email)) {
            $this->errors[] = 'そのユーザー名またはメールアドレスは既に使用されています。';
            return;
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            $this->errors[] = $user_id->get_error_message();
        } else {
            $this->send_mail('register', $email, $username);

            if (!empty($this->options['auto_login_after_register'])) {
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
            }

            $redirect = !empty($this->options['register_redirect_url']) ? $this->options['register_redirect_url'] : home_url();
            $redirect = add_query_arg('edel_status', 'registered', $redirect);

            wp_safe_redirect($redirect);
            exit;
        }
    }

    // --- ロジック: パスワードリセット要求 ---
    private function process_reset_request() {
        $user_input = sanitize_text_field($_POST['user_login']);

        // ユーザー特定（メール or ID）
        $user = get_user_by('email', $user_input);
        if (!$user && !is_email($user_input)) {
            $user = get_user_by('login', $user_input);
        }

        if ($user) {
            $key = get_password_reset_key($user);
            if (!is_wp_error($key)) {
                // 再設定用ページURL取得
                $base_url = isset($this->options['reset_password_page_url']) ? $this->options['reset_password_page_url'] : home_url();

                // ★修正1: rawurlencode を削除 (add_query_argが自動で行うため)
                $reset_url = add_query_arg(array('key' => $key, 'login' => $user->user_login), $base_url);

                // ★修正2: テスト用にURLをログに出力 (本番時は削除推奨)
                error_log('[Edel Membership] Password Reset URL: ' . $reset_url);

                $this->send_mail('reset', $user->user_email, $user->user_login, $reset_url);
            }
        }

        // セキュリティのため、成功失敗に関わらず同じメッセージを表示
        $this->errors[] = '登録メールアドレス宛に再設定リンクを送信しました。（メールが届かない場合は入力内容を確認してください）';
    }

    // --- ロジック: 新パスワード保存 ---
    private function process_reset_new() {
        $key   = $_POST['key'];
        $login = $_POST['login'];
        $pass1 = $_POST['pass1'];
        $pass2 = $_POST['pass2'];

        if (empty($pass1) || empty($pass2)) {
            $this->errors[] = 'パスワードを入力してください。';
            return;
        }

        if ($pass1 !== $pass2) {
            $this->errors[] = 'パスワードが一致しません。';
            return;
        }

        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            $this->errors[] = 'このリンクは無効か、有効期限が切れています。もう一度リセットリクエストを行ってください。';
        } else {
            reset_password($user, $pass1);

            // ★変更: ログインページのURLを設定
            // ※ '/login/' の部分は、ログインページのスラッグに合わせて変更してください
            $login_url = home_url('/login/');

            // リンク付きのメッセージに変更
            $this->errors[] = 'パスワードを変更しました。<br><a href="' . esc_url($login_url) . '" class="edel-msg-link">ログインページへ移動する</a>';
        }
    }

    // --- ヘルパー: reCAPTCHA有効判定 ---
    private function is_recaptcha_active() {
        return !empty($this->options['recaptcha_enabled']) && !empty($this->options['recaptcha_site_key']);
    }

    // --- ヘルパー: reCAPTCHA検証 ---
    private function verify_recaptcha() {
        if (!$this->is_recaptcha_active()) return true;

        $token = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        if (!$token) {
            error_log('[Edel Membership] Error: トークンが送信されていません。');
            return false;
        }

        $secret = $this->options['recaptcha_secret_key'];
        $response = wp_remote_post("https://www.google.com/recaptcha/api/siteverify", array(
            'body' => array('secret' => $secret, 'response' => $token)
        ));

        if (is_wp_error($response)) {
            error_log('[Edel Membership] Connection Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['success']) || !$body['success']) {
            error_log('[Edel Membership] API Error: successがfalseです。エラーコード: ' . print_r($body['error-codes'] ?? 'unknown', true));
            return false;
        }

        // 閾値を0.1で維持（テスト用）
        $threshold = 0.1;
        $score = isset($body['score']) ? floatval($body['score']) : 0;

        if ($score < $threshold) {
            error_log("[Edel Membership] Score Error: スコアが低すぎます。User Score: {$score} / Threshold: {$threshold}");
            return false;
        }

        return true;
    }

    // --- ヘルパー: メール送信 ---
    private function send_mail($type, $email, $username, $extra_url = '') {
        add_filter('wp_mail_from', function ($original) {
            return !empty($this->options['mail_from_email']) ? $this->options['mail_from_email'] : $original;
        });
        add_filter('wp_mail_from_name', function ($original) {
            return !empty($this->options['mail_from_name']) ? $this->options['mail_from_name'] : $original;
        });

        if ($type === 'register') {
            $subject = isset($this->options['register_mail_subject']) ? $this->options['register_mail_subject'] : "[{blogname}] 登録完了のお知らせ";
            $body    = isset($this->options['register_mail_body']) ? $this->options['register_mail_body'] : "{username} 様\n\n会員登録が完了しました。";
        } else {
            $subject = isset($this->options['reset_mail_subject']) ? $this->options['reset_mail_subject'] : "[{blogname}] パスワードの再設定";
            $body    = isset($this->options['reset_mail_body']) ? $this->options['reset_mail_body'] : "{username} 様\n\nパスワードリセットのリクエストを受け付けました。\n以下のリンクをクリックして設定を行ってください。\n{reset_url}";
        }

        $body = str_replace('{username}', $username, $body);
        $body = str_replace('{blogname}', get_bloginfo('name'), $body);
        $body = str_replace('{login_url}', (!empty($this->options['login_redirect_url']) ? $this->options['login_redirect_url'] : wp_login_url()), $body);
        if ($extra_url) $body = str_replace('{reset_url}', $extra_url, $body);

        wp_mail($email, $subject, $body);

        remove_all_filters('wp_mail_from');
        remove_all_filters('wp_mail_from_name');
    }

    // --- ショートコード ---

    function shortcode_login() {
        if (is_user_logged_in()) {
            $logout_redirect = !empty($this->options['logout_redirect_url']) ? $this->options['logout_redirect_url'] : home_url();
            $logout_url = wp_logout_url($logout_redirect);

            $html  = '<div class="edel-container">';
            $html .= '<p class="edel-desc">すでにログイン中です。</p>';
            $html .= '<a href="' . esc_url($logout_url) . '" class="edel-btn">ログアウト</a>';
            $html .= '</div>';
            return $html;
        }
        return $this->render_form('login');
    }

    function shortcode_register() {
        if (is_user_logged_in()) {
            $redirect_url = !empty($this->options['login_redirect_url']) ? $this->options['login_redirect_url'] : home_url();
            return '<script>window.location.href="' . esc_url($redirect_url) . '";</script>';
        }
        return $this->render_form('register');
    }

    function shortcode_reset_request() {
        if (is_user_logged_in()) return "<p>既にログインしています。</p>";
        return $this->render_form('reset_request');
    }

    function shortcode_reset_new() {
        if (!isset($_GET['key']) || !isset($_GET['login'])) {
            return "<p class='edel-error'>無効なアクセスです。</p>";
        }
        return $this->render_form('reset_new');
    }

    // --- フォームHTML生成 ---
    private function render_form($type) {
        $nonce = wp_nonce_field('edel_action_nonce', '_wpnonce', true, false);
        $is_email_only = !empty($this->options['email_only_register']);
        $recaptcha_active = $this->is_recaptcha_active();

        $errors_html = '';
        if (!empty($this->errors)) {
            $errors_html = '<div class="edel-errors"><ul>';
            foreach ($this->errors as $err) $errors_html .= "<li>{$err}</li>";
            $errors_html .= '</ul></div>';
        }

        $html = "<div class='edel-container'>{$errors_html}<form method='post' class='edel-form edel-form-{$type}'>";
        $html .= "<input type='hidden' name='edel_action' value='{$type}'>";

        if ($recaptcha_active) {
            $html .= "<input type='hidden' name='g-recaptcha-response' class='edel-recaptcha-response'>";
        }

        switch ($type) {
            case 'login':
                $login_label = $is_email_only ? 'メールアドレス' : 'ユーザー名 / メールアドレス';
                $html .= '<div class="edel-field"><label>' . esc_html($login_label) . '</label><input type="text" name="log" required></div>';
                $html .= '<div class="edel-field"><label>パスワード</label><input type="password" name="pwd" required></div>';
                $html .= '<div class="edel-field"><label class="edel-inline-label"><input type="checkbox" name="remember"> ログイン状態を保存</label></div>';
                $html .= '<button type="submit">ログイン</button>';
                break;

            case 'register':
                if (!$is_email_only) {
                    $html .= '<div class="edel-field"><label>ユーザー名</label><input type="text" name="user_login" required></div>';
                }
                $html .= '<div class="edel-field"><label>メールアドレス</label><input type="email" name="user_email" required></div>';
                $html .= '<div class="edel-field"><label>パスワード</label><input type="password" name="user_pass" required></div>';
                $html .= '<button type="submit">登録する</button>';
                break;

            case 'reset_request':
                $html .= '<p class="edel-desc">登録時のメールアドレスを入力してください。</p>';
                $html .= '<div class="edel-field"><label>メールアドレス</label><input type="text" name="user_login" required></div>';
                $html .= '<button type="submit">再設定メールを送信</button>';
                break;

            case 'reset_new':
                $html .= '<input type="hidden" name="key" value="' . esc_attr($_GET['key']) . '">';
                $html .= '<input type="hidden" name="login" value="' . esc_attr($_GET['login']) . '">';
                $html .= '<div class="edel-field"><label>新しいパスワード</label><input type="password" name="pass1" required></div>';
                $html .= '<div class="edel-field"><label>新しいパスワード(確認)</label><input type="password" name="pass2" required></div>';
                $html .= '<button type="submit">パスワード変更</button>';
                break;
        }

        $html .= $nonce . "</form></div>";
        return $html;
    }
}
