<?php

/**
 * Description of analytics
 *
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;

class Finance extends Admin {

    /**
     * All earnings records of persons
     * 1 - paid, 0 - unpaid
     * 
     * @before _secure, changeLayout, _admin
     */
    public function pending() {
        $this->seo(array("title" => "Records Finance", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $database = Registry::get("database");
        $where = array();
        $live = RequestMethods::get("live", 0);
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        if (RequestMethods::get("user_id")) {
            $where = array("user_id = ?" => RequestMethods::get("user_id"));
        }
        
        $accounts = Account::all($where, array("user_id", "balance"), "balance", "desc", $limit, $page);
        
        $view->set("accounts", $accounts);
        $view->set("count", Account::count($where));
        $view->set("page", $page);
        $view->set("limit", $limit);
        $view->set("live", $live);
    }

    /**
     * Finds the earning from a website
     * @before _secure, changeLayout, _admin
     */
    public function earnings() {
        $this->seo(array("title" => "Earnings Finance", "view" => $this->getLayoutView()));
        $view = $this->getActionView(); $amount = 0;
        $website = RequestMethods::get("website", "http://www.khattimithi.com");

        $where = array("url LIKE ?" => "%{$website}%");
        $items = Item::all($where, array("id"));
        $count = Item::count($where);

        foreach ($items as $item) {
            $database = Registry::get("database");
            $earnings = $database->query()->from("stats", array("SUM(amount)" => "earn"))->where("item_id=?",$item->id)->all();
            $amount += $earnings[0]["earn"];
        }
        
        $view->set("items", $items);
        $view->set("count", $count);
        $view->set("website", $website);
        $view->set("amount", $amount);
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function makepayment($user_id) {
        $this->seo(array("title" => "Make Payment", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $payee = User::first(array("id = ?" => $user_id), array("id", "name", "email", "phone"));
        $account = Account::first(array("user_id = ?" => $user_id));
        $bank = Bank::first(array("user_id = ?" => $user_id));

        if (RequestMethods::post("action") == "payment") {
            $transaction = new Transaction(array(
                "user_id" => $user_id,
                "amount" => $account->balance,
                "ref" => RequestMethods::post("ref"),
                "live" => 1
            ));
            $transaction->save();
            $account->balance = 0;
            $account->save();

            $this->notify(array(
                "template" => "makePayment",
                "subject" => "Payments From Clicks99 Team",
                "user" => $payee,
                "transaction" => $transaction,
                "bank" => $bank
            ));

            self::redirect("/finance/pending");
        }

        $view->set("payee", $payee);
        $view->set("account", $account);
        $view->set("bank", $bank);
    }

    /**
     * Earning on a Content
     * @before _secure, changeLayout, _admin
     */
    public function content($id='') {
        $this->seo(array("title" => "Content Finance", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $item = Item::first(array("id = ?" => $id));

        $earn = 0;
        $earnings = Earning::all(array("item_id = ?" => $item->id), array("amount"));
        foreach ($earnings as $earning) {
            $earn += $earning->amount;
        }

        $links = Link::count(array("item_id = ?" => $item->id));
        $rpm = RPM::count(array("item_id = ?" => $item->id));

        $view->set("item", $item);
        $view->set("earn", $earn);
        $view->set("links", $links);
        $view->set("rpm", $rpm);
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function transactions() {
        $this->seo(array("title" => "Payments", "view" => $this->getLayoutView()));

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $user_id = RequestMethods::get("user_id");
        if (!empty($user_id)) {
            $where = array(
                "user_id = ?" => $user_id
            );
        } else{
            $where = array();
        }

        $view = $this->getActionView();
        $transactions = Transaction::all($where, array("*"), "created", "desc", $limit, $page);
        $count = Transaction::count($where);

        $view->set("transactions", $transactions);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("user_id", $user_id);
    }
    
}