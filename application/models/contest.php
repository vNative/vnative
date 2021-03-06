<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Services\Db as Db;
use Shared\Utils as Utils;
use Framework\RequestMethods as RequestMethods;
class Contest extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_org_id;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required
     */
    protected $_title;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_type;

    /**
     * @column
     * @readwrite
     * @type datetime
     * @index
     */
    protected $_start;


    /**
     * @column
     * @readwrite
     * @type datetime
     * @index
     */
    protected $_end;

    public static function exists($oid, $opts = []) {
        $oid = Db::convertType($oid);
        $dateQuery = Utils::dateQuery($opts);

        $multiple = $opts['multiple'] ?? false;
        if ($multiple) {
            $query = "all";
        } else {
            $query = "first";
        }
        $inv_exist = self::$query(['$or' => [
            [   // $start, $end exists in b/w contest start, end
                "org_id" => $oid,
                "start" => ['$lte' => $dateQuery['start'], '$lte' => $dateQuery['end']],
                "end" => ['$gte' => $dateQuery['start'], '$gte' => $dateQuery['end']]
            ], [    // contest start in b/w $start, $end
                "org_id" => $oid,
                "start" => Db::dateQuery($start, $end),
                "end" => ['$gte' => $dateQuery['start'], '$gte' => $dateQuery['end']]
            ], [    // contest end exists b/w $start, $end
                "org_id" => $oid,
                "start" => ['$lte' => $dateQuery['start'], '$lte' => $dateQuery['end']],
                "end" => Db::dateQuery($start, $end)
            ], [    // contest start and end exists b/w $start, $end
                "org_id" => $oid,
                "start" => Db::dateQuery($start, $end),
                "end" => Db::dateQuery($start, $end)
            ]
        ]]);

        return $inv_exist;
    }

    public static function updateContests($controller) {
        $org = $controller->org;
        $fields = ['title', 'start', 'end'];
        $id = RequestMethods::post('contest_id');
        if ($id) {
            $contest = self::first(['_id' => $id, 'org_id' => $org->_id]);
        } else {
            $contest = new self([ 'org_id' => $org->_id ]);
        }
        foreach ($fields as $f) {
            $contest->$f = RequestMethods::post($f);
        }

        // depending on the contest type process the fields
        $type = RequestMethods::post('type');
        switch ($type) {
            case 'clickRange': // process all the ranges
                $meta = $contest->meta; $oldCond = isset($meta['condition']) ? $meta['condition'] : [];
                $condition = [];
                $rangeStart = RequestMethods::post('rangeStart', []);
                $rangeEnd = RequestMethods::post('rangeEnd', []);
                $rangePrize = RequestMethods::post('rangePrize', []);
                for ($i = 0, $total = count($rangeStart); $i < $total; ++$i) {
                    $condition[] = [
                        'start' => $rangeStart[$i],
                        'end' => $rangeEnd[$i],
                        'prize' => $rangePrize[$i]
                    ];
                }

                // prevent winners from being ovverriden
                if (count($oldCond) === count($condition)) {
                    $i = 0;
                    for ($i = 0, $total = count($condition); $i < $total; ++$i) {
                        if ($condition[$i]['start'] == $oldCond[$i]['start'] && $condition[$i]['end'] == $oldCond[$i]['end']) {
                            $condition[$i]['winners'] = isset($oldCond[$i]['winners']) ? $oldCond[$i]['winners'] : [];
                        }
                    }
                }

                $meta['condition'] = $condition;
                $contest->meta = $meta;
                break;
            
            case 'topEarner':
                $meta = $contest->meta; $condition = $meta['condition'];
                $condition['prize'] = RequestMethods::post('topEarnerPrize');
                $condition['topEarnerCount'] = RequestMethods::post('topEarnerCount');

                $meta['condition'] = $condition; $contest->meta = $meta;
                break;

            default:
                return array('message' => 'Invalid Request!!');
        }
        $contest->type = $type;

        if ($contest->validate()) {
            $contest->save();
            return array('message' => 'Contest Added successfully!!');
        } else {
            return array('message' => 'Please fill the required fields!!');
        }
    }
}
