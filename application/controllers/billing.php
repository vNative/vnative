<?php
/**
 * Controller to manage all billings of publishers and advertisers
 *
 * @author Faizan Ayubi
 */
use Shared\Mail as Mail;
use Shared\Utils as Utils;
use Shared\Services\Db as Db;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;
use Framework\RequestMethods as RequestMethods;

class Billing extends Admin {

	/**
     * @before _secure
     */
    public function affiliates() {
        $this->seo(array("title" => "Billing"));
        $view = $this->getActionView();
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $invoices = \Invoice::all(['utype = ?' => 'publisher', 'org_id' => $this->org->_id]);

        $view->set('invoices', $invoices)
            ->set('start', $start)
            ->set('end', $end);
    }

    /**
     * @before _secure
     */
    public function createinvoice() {
        $this->seo(array("title" => "Create Invoice"));
        $view = $this->getActionView();

        $start = RequestMethods::get("start");
        $end = RequestMethods::get("end");

        $diff = date_diff(new DateTime($start), new DateTime($end));
        $dateQuery = Utils::dateQuery($start, $end);
        $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];

        $user_id = RequestMethods::get("user_id", null);
        $query = [ "user_id" => Utils::mongoObjectId($user_id)];

        if($user_id) {
            $user = \User::first(['type = ?' => 'publisher', 'org_id' => $this->org->_id, 'id = ?' => $user_id]);
            $view->set('affiliate', $user);
            $performances = Performance::all(["user_id = ?" => $user_id]);
            $view->set('performances', $performances);
        } else {
            $affiliates = \User::all(['type = ?' => 'publisher', 'org_id' => $this->org->_id], ['id', 'name']);
            $view->set('affiliates', $affiliates);
        }

        if (RequestMethods::post("action") == "cinvoice") {
            $invoice = new Invoice([
                "org_id" => $this->org->id,
                "user_id" => $this->user->id,
                "utype" => $user->type,
                "start" => $start,
                "end" => $end,
                "period" => $diff->format("%a"),
                "amount" => RequestMethods::post("amount")
            ]);
            $invoice->save();

            $this->redirect("/billing/affiliates.html");
        }
        $view->set('user_id', $user_id)
            ->set('start', $start)
            ->set('end', $end);
    }

    /**
     * @before _secure
     */
    public function advertisers() {
        $this->seo(array("title" => "Billing"));
        $view = $this->getActionView();
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $invoices = \Invoice::all(['utype = ?' => 'advertiser', 'org_id' => $this->org->_id]);

        $view->set('invoices', $invoices)
            ->set('start', $start)
            ->set('end', $end);
    }
}