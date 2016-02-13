<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */

class CRON extends Auth {

    public function index() {
        $this->noview();
        $this->log("CRON Started");
        $this->verify();
        $this->log("CRON Ended");
        $this->passwordmeta();
    }
    
    protected function verify() {
        $yesterday = strftime("%Y-%m-%d", strtotime('-1 day'));
        $startdate = date('Y-m-d', strtotime("-20 day"));
        $enddate = date('Y-m-d', strtotime("now"));
        $where = array(
            "live = ?" => true,
            "created >= ?" => $startdate,
            "created < ?" => $enddate
        );
        $links = Link::all($where, array("id", "short", "item_id", "user_id"));

        foreach ($links as $link) {
            $data = $link->stat($yesterday);
            if ($data["click"] > 30) {
                $this->saveStats($data, $link);

                //sleep the script
                sleep(1);
            }
        }
    }

    protected function saveStats($data, $link) {
        $stat = Stat::first(array("link_id = ?" => $link->id));
        if(!$stat) {
            $stat = new Stat(array(
                "user_id" => $link->user_id,
                "link_id" => $link->id,
                "item_id" => $link->item_id,
                "click" => $data["click"] - 4,
                "amount" => $data["earning"] - 0.6,
                "rpm" => $data["rpm"]
            ));
            $stat->save();
        } else {
            $today =strtotime(date('Y-m-d', strtotime("now")));
            $modified = strtotime($stat->modified);

            if($modified < $today) {
                $stat->click += $data["click"];
                $stat->amount += $data["earning"];
                $stat->rpm = $data["rpm"];
                $stat->save();
            }
        }
        
        $stat->removeProperty();
        $output = '<pre>'. print_r($stat, true). '</pre>';
        $this->log($output);
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
