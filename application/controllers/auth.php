<?php
/**
 * @author Faizan Ayubi
 */
use \Curl\Curl;
use Shared\{Utils, Mail, Controller};
use Framework\{Registry, ArrayMethods, RequestMethods as RM};

class Auth extends Controller {
    /**
     * @protected
     */
    public function _csrfToken() {
        $session = Registry::get("session");
        $csrf_token = Framework\StringMethods::uniqRandString(44);
        $session->set('Auth\Request:$token', $csrf_token);

        if ($this->actionView) {
            $this->actionView->set('__token', $csrf_token);
        }
    }

    public function verifyToken($token = null) {
        $session = Registry::get("session");
        $csrf = $session->get('Auth\Request:$token');

        if ($csrf && $csrf === $token) {
            return true;
        }
        return false;
    }

    public function __construct($options = []) {
        parent::__construct($options);

        $start = RM::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RM::get("end", strftime("%Y-%m-%d", strtotime('now')));

        if ($this->actionView) {
            $this->actionView->set(['start' => $start, 'end' => $end]);
        }

        $host = RM::server('HTTP_HOST');
        if (strpos($host, "vnative.com")) {
            $domain = explode(".", $host);
            $domain = array_shift($domain);
            $q = ["domain = ?" => $domain];
        } else {
            $domain = $host;
            $q = ["url = ?" => $domain];
        }
        $this->domain = $domain;

        if (!is_object($this->org) || !property_exists($this->org, '__id')) {
            $org = \Organization::first($q);

            if (!$org || $org->live !== true) {
                $this->_404();
            } else {
                $this->org = $org;
            }
        }
        // Organization account banned
        if ($this->org->live !== true) $this->_404();
    }
    
    /**
     * @before _session
     * @after _csrfToken
     */
    public function login() {
        $this->seo(array("title" => "Login", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $token = RM::post("token", '');
        if (RM::post("action") == "login" && $this->verifyToken($token)) {
            $this->_login($this->org, $view);
        }
    }

    protected function _login($org, $view) {
        $session = Registry::get("session");
        $email = strtolower(trim(RM::post("email"))); $pass = RM::post("password");
        $user = \User::first(["org_id = ?" => $org->_id, "email = ?" => $email]);

        if (!$user) {
            return $view->set('message', 'Invalid credentials');
        } else if (sha1($pass) != $user->password) {
            return $view->set('message', 'Invalid credentials');
        } else if (!$user->live) {
            return $view->set('message', 'User account deactivated!!');
        }
        $session->erase('Auth\Login:$token');   // erase login token

        $user->login = \Shared\Services\Db::time();
        $user->save();
        $this->_loginRedirect($user, $org);
    }

    protected function _loginRedirect($user, $org) {
        $session = Registry::get("session");
        $this->setUser($user); $this->setOrg($org);
        $beforeLogin = $session->get('$beforeLogin');
        if ($beforeLogin) {
            $session->erase('$beforeLogin');
            $this->redirect($beforeLogin);
        }

        $type = $user->type;
        if (!in_array($type, ['publisher', 'advertiser'])) {
            $type = "admin";
        }
        $this->redirect("/{$type}/index.html");
    }

    /**
     * @before _session
     */
    public function forgotpassword() {
        $this->seo(array("title" => "Forgot Password", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $view->set('organization', $this->org);

        // @todo install reCaptcha
        if (RM::post("action") == "forgot") {
            $message = $this->_forgotPassword($this->org);
            $view->set("message", $message);
        }
    }

    /**
     * @before _session
     */
    public function resetpassword($token) {
        $this->seo(array("title" => "Forgot Password", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $meta = Meta::search('resetpass', (object) ['value' => $token]);
        if (!$meta) {
            $this->redirect("/");
        }

        // @todo install reCaptcha
        if (RM::post("action") == "change") {
            $pass = RM::post("password");
            $user = User::first(array("id = ?" => $meta->propid));
            if ($pass == RM::post("npassword")) {
                $user->password = sha1($pass); $user->save();
                $meta->delete();
                $view->set("message", 'Password changed successfully now <a href="/login.html">Login</a>');
            } else{
                $view->set("message", 'Password Does not match');
            }
        }
    }

    protected function _forgotPassword($org) {
        $exist = User::first(array("email = ?" => RM::post("email")), array("id", "email", "name"));
        if ($exist) {
            $meta = new Meta(array(
                "propid" => $exist->id,
                "prop" => "resetpass",
                "value" => uniqid()
            ));
            $meta->save();
            Mail::send(array(
                "template" => "forgotpass",
                "subject" => "New Password Requested",
                "user" => $exist,
                "meta" => $meta, "org" => $this->org
            ));
        }
        return "Password Reset Email Sent Check Your Email. Check in Spam too.";
    }

    protected function _publisherRegister($org, $view) {
        $platformUrl = RM::post("platform", '');

        try {
            $platform = new \Platform([
                'url' => $platformUrl
            ]);
        } catch (\Exception $e) {
            return $view->set('message', $e->getMessage());
        }
        
        $user = User::addNew('publisher', $org, $view);
        if ($user === false) return;
        $pass = $user->password;

        $user->password = sha1($pass);
        $output = Shared\Services\User::customFields($user, $org);
        if (!$output['success']) return $view->set($output);
        $user->save();

        Mail::send([
            'user' => $user, 'org' => $this->org,
            'template' => 'pubRegister', 'pass' => $pass,
            'subject' => $this->org->name . ' Support'
        ]);
        
        $platform->user_id = $user->_id;
        $platform->save();
        $view->set('message', "Registered Successfully");
    }

    protected function _advertiserRegister($org, $view) {
        $platformUrl = RM::post("platform", '');

        try {
            $platform = new \Platform([
                'url' => $platformUrl
            ]);
        } catch (\Exception $e) {
            return $view->set('message', $e->getMessage());
        }

        $user = User::addNew('advertiser', $org, $view);
        if ($user === false) return;
        $pass = $user->password;

        $user->password = sha1($pass);
        $user->save();
        
        $platform->user_id = $user->_id;
        $platform->save();

        Mail::send([
            'user' => $user, 'org' => $this->org,
            'template' => 'advertReg', 'pass' => $pass,
            'subject' => $this->org->name . ' Support'
        ]);
        return $view->set('message', "Registered Successfully");
    }

    /**
     * Login as a User
     * @before _admin
     */
    public function loginas($user_id) {
        $session = Registry::get("session");
        $session->set("admin_user_id", $this->user->id);
        $this->setUser(false);
        $user = User::first(array("_id = ?" => $user_id));
        $org = Organization::first(["_id = ?" => $user->org_id]);
        $this->_loginRedirect($user, $org);
    }

    public function logout() {
        $session = Registry::get("session");
        $this->setUser(false);

        $admin = $session->get("admin_user_id");
        if (!$admin) {
            session_destroy();
            $this->redirect("/");
        } else {
            $user = User::first(["id = ?" => $admin]);
            $org = Organization::first(["_id = ?" => $user->org_id]);
            $session->erase("admin_user_id");
            $this->_loginRedirect($user, $org);
        }
    }

        protected function _postback($case, $extra = []) {
        $view = $this->getActionView();
        switch ($case) {
            case 'add':
                $search = ["org_id" => $this->org->_id, "user_id" => $this->user->_id, "event" => RM::post("event")];
                if (isset($extra['ad'])) {
                    $search["ad_id"] = $extra['ad']->_id;
                }
                $foundPostback = PostBack::first($search);

                if (RM::post('action') === 'addCallback' && !$foundPostback) {
                    $postback = new PostBack(array_merge($search, [
                        "data" => RM::post("data"),
                        "type" => RM::post("type")
                    ]));
                    $postback->save();
                    $view->set('message', 'Postback Saved Successfully');
                } else {
                    $view->set('message', 'Postback already added');
                }
                break;
            
            case 'delete':
                $postback = PostBack::first(["_id" => RM::get("postback_id"), "user_id" => $this->user->_id, "org_id" => $this->org->_id]);
                // check for valid request as a fallback
                if (RM::type() === 'DELETE' && RM::get('removeCallback') && $postback) {
                    $postback->delete();
                    $view->set('message', "Postback removed!!");
                } else {
                    $view->set('message', "Invalid Request!!");
                }
                break;

            case 'show':
                $query = ['user_id' => $this->user->_id, 'org_id' => $this->org->_id];
                if (isset($extra['ad'])) {
                    $query["ad_id"] = $extra['ad']->_id;
                    $postbacks = PostBack::all($query);
                } else {
                    $postbacks = PostBack::all($query); $ans = [];
                    foreach ($postbacks as $p) {
                        if (!$p->ad_id) {
                            $ans[$p->_id] = $p;
                        }
                    }
                    $postbacks = $ans;
                }
                $view->set('postbacks', $postbacks);
                break;
        }
    }

    /**
     * @before _admin
     */
    public function delete($record_id) {
        $this->JSONview();

        if (RM::type() !== 'DELETE') {
            $this->_404();
        }
    }

    protected function widgets() {
        $meta = $this->org->meta;
        if (isset($meta['widget']) && isset($meta['widget']['top10pubs']) && count($meta['widget']['top10pubs']) > 0) {
            $widgets = $meta['widget'];
            return [
                'publishers' => $widgets['top10pubs'] ?? [],
                'ads' => Shared\Services\Campaign::displayData($widgets['top10ads'] ?? [])
            ];
        } else { // fallback case
            return [
                'publishers' => [],
                'ads' => []
            ];
        }
    }

    protected function perf($clicks, $arr, $dq = []) {
        $perf = new Performance(); $commissions = [];
        $classify = \Click::classify($clicks, 'adid');
        foreach ($classify as $key => $value) {
            $countryWise = \Click::classify($value, 'country');

            foreach ($countryWise as $country => $records) {
                $adClicks = count($records); $updateData = [];

                $extra = array_merge($arr, $dq);
                $info = \Commission::campaignRate($key, $commissions, $country, $extra);

                $earning = \Ad::earning($info, $adClicks);
                \Framework\ArrayMethods::copy($earning, $updateData);
                $perf->update($updateData);
            }
        }

        return $perf;
    }
}
