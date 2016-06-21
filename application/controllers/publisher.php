<?php
/**
 * Description of publisher
 *
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Publisher extends Advertiser {

    /**
     * @before _secure, _layout
     */
    public function index() {
        $this->seo(array("title" => "Monetize", "description" => "Stats for your Data", "view" => $this->getLayoutView()));
        $view = $this->getActionView();$session = Registry::get("session");
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('now')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $customer = $session->get("customer");
        
        $view->set("start", $start);
        $view->set("end", $end);
        $view->set("customer", $customer);
    }

    /**
     * @before _secure, _layout
     */
    public function adunits() {
        $this->seo(array("title" => "Ad units", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $where = array("user_id" => $this->user->id);

        $adunits = \Models\Mongo\AdUnit::all($where, array("name", "category", "live", "created"), "created", -1, $limit, $page);
        $count = \Models\Mongo\AdUnit::count($where);

        $view->set("adunits", $adunits);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", $count);
    }

    /**
     * @before _secure, _layout
     */
    public function createadunit() {
        $this->seo(array("title" => "Create Ad unit", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if (RequestMethods::post("action") == "adunit") {
            $adunit = new \Models\Mongo\AdUnit(array(
                "user_id" => $this->user->id,
                "name" => RequestMethods::post("name"),
                "category" => RequestMethods::post("category"),
                "type" => json_encode(RequestMethods::post("type"))
            ));
            $adunit->save();

            $this->redirect("/publisher/adunits.html");
        }
    }

    public function aucode() {
        $this->JSONview();
        $view = $this->getActionView();

        $adunit = \Models\Mongo\AdUnit::first(array("_id" => RequestMethods::get("auid")));
        $view->set('code', $this->_code($adunit));
        $view->set('adunit', $adunit);
    }

    protected function _code($adunit) {
        switch ($adunit->category) {
            case 'native':
                $code = '<script>(function (we, a, r, e, vnative){we["vNativeObject"]=vnative;we[vnative]=we[vnative]||function(){(i[vnative].q=i[r].q || []).push(arguments)};var x,y;x=a.createElement(r),y=a.getElementsByTagName(r)[0];x.async=true;x.src=e;y.parentNode.insertBefore(x, y);}(window,document,"script","//serve.vnative.com/js/native.js","vn"));
                </script>';
                $code .= '<ins class="byvnative"
                            data-client="pub-'. $this->user->id. '"
                            data-slot="'. $adunit->_id .'"
                            data-format="all"></ins>';
                break;
            
            case 'fbia':
                $code = '<figure class="op-ad">
                              <iframe src="//serve.vnative.com/public/assets/images/320x50.png" height="60" width="320"></iframe>
                        </figure>';
                break;

            case 'amp':
                # code...
                break;

            case 'notify':
                # code...
                break;
        }
        return htmlentities($code);
    }
}