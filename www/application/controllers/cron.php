<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */

class CRON extends Shared\Controller {

    public function index() {
        $this->noview();
        $this->log("CRON Started");
        $this->ctracker();
        $this->log("CRON Ended");
        
        $this->log("Password Meta Started");
        $this->passwordmeta();
        $this->log("Password Meta Ended");
    }

    protected function ctracker() {
        $date = date('Y-m-d', strtotime("now"));
        $where = array(
            "live = ?" => true,
            "created >= ?" => date('Y-m-d', strtotime("-20 day")),
            "created < ?" => date('Y-m-d', strtotime("now"))
        );
        $links = Link::all($where, array("id", "short", "item_id", "user_id"));
        $accounts = $this->verify($date, $links);
        
        sleep(10);
        if (!empty($accounts)) {
            $this->log("Account Started");
            $this->saveAccount($accounts);
            $this->log("Account Ended");
        }
    }
    
    protected function verify($today, $links) {
        $accounts = array();
        $yesterday = date('Y-m-d', strtotime($today . " -1 day"));

        foreach ($links as $link) {
            $data = $link->stat($yesterday);
            if ($data["click"] > 10) {
                $stat = $this->saveStats($data, $link, $today);
                if (array_key_exists($stat->user_id, $accounts)) {
                    $accounts[$stat->user_id] += $data["earning"];
                } else {
                    $accounts[$stat->user_id] = $data["earning"];
                }
                //sleep the script
                sleep(1);
            }
        }
        return $accounts;
    }

    protected function saveStats($data, $link, $today) {
        $stat = Stat::first(array("link_id = ?" => $link->id));
        if(!$stat) {
            $stat = new Stat(array(
                "user_id" => $link->user_id,
                "link_id" => $link->id,
                "item_id" => $link->item_id,
                "click" => $data["click"],
                "amount" => $data["earning"],
                "rpm" => $data["rpm"],
                "live" => 1,
                "updated" => $today
            ));
            $stat->save();
            $output = "New {$stat->id} - Done";
        } else {
            $modified = strtotime($stat->updated);
            $output = "{$stat->id} - Dropped";
            if($modified < strtotime($today)) {
                $stat->click += $data["click"];
                $stat->amount += $data["earning"];
                $stat->rpm = $data["rpm"];
                $stat->updated = $today;
                $stat->save();
                $output = "Updated {$stat->id} - Done";
            }
        }

        $this->log($output);
        return $stat;
    }

    protected function saveAccount($accounts) {
        foreach ($accounts as $key => $value) {
            $account = Account::first(array("user_id = ?" => $key));
            if (!$account) {
                $account = new Account(array(
                    "user_id" => $key,
                    "balance" => $value,
                    "live" => 1
                ));
                $account->save();
            } else {
                $account->balance += $value;
                $account->save();
            }
            $transaction = new Transaction(array(
                "user_id" => $key,
                "amount" => $value,
                "ref" => "linkstracking"
            ));
            $transaction->save();
        }
    }

    public function fraud() {
        $this->noview();
        $this->log("Fraud Started");
        $now = date('Y-m-d', strtotime("now"));
        $fp = fopen(APP_PATH . "/logs/fraud-{$now}.csv", 'w');
        fputcsv($fp, array("USER_ID", "STAT_ID", "LINK_ID"));

        $users = Stat::all(array(), array("DISTINCT user_id"), "amount", "DESC");
        foreach ($users as $user) {
            $this->log("Checking User - {$user->user_id}");
            $stats = Stat::all(array("user_id = ?" => $user->user_id), array("id", "link_id", "user_id"));
            foreach ($stats as $stat) {
                if ($stat->is_bot()) {
                    fputcsv($fp, array($stat->user_id, $stat->id, $stat->link_id));
                    $this->log("Fraud - {$stat->link_id}");
                }
                sleep(1);
            }
        }
        fclose($fp);
        $this->log("Fraud Ended");
    }

    protected function reset() {
        $db = Framework\Registry::get("database");
        $db->sync(new Stat);
        $links = Link::all(array(), array("id", "short", "item_id", "user_id"));
        $startdate = date('Y-m-d', strtotime("-6 day"));
        $enddate = date('Y-m-d', strtotime("-1 day"));
        $diff = date_diff(date_create($startdate), date_create($enddate));
        for ($i = 0; $i <= $diff->format("%a"); $i++) {
            $date = date('Y-m-d', strtotime($startdate . " +{$i} day"));
            foreach ($links as $link) {
                $data = $link->stat($date);
                if ($data["click"] > 30) {
                    $this->saveStats($data, $link);

                    //sleep the script
                    sleep(1);
                }
            }
        }
    }

    protected function passwordmeta() {
        $now = date('Y-m-d', strtotime("now"));
        $meta = Meta::all(array("property = ?" => "resetpass", "created < ?" => $now));
        foreach ($meta as $m) {
            $m->delete();
        }
    }
    
}
