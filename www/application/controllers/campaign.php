<?php

/**
 * Description of content
 *
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use \WebBot\lib\WebBot\Bot as Bot;

class Campaign extends Publisher {

    protected $rpm = array(
        "IN" => 135,
        "US" => 220,
        "CA" => 220,
        "AU" => 220,
        "GB" => 220,
        "NONE" => 80
    );

    /**
     * @before _secure, publisherLayout
     */
    public function index() {
        $this->seo(array("title" => "Link Share Campaign", "description" => "All campaign sorted by newly added", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        $title = RequestMethods::get("title", "");
        $category = RequestMethods::get("category", "");
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 12);

        $where = array(
            "title LIKE ?" => "%{$title}%",
            "live = ?" => true
        );
        
        $items = Item::all($where, array("id", "title", "image", "url", "description"), "created", "desc", $limit, $page);
        $count = Item::count($where);

        $view->set("limit", $limit);
        $view->set("title", $title);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("items", $items);
        $view->set("category", $category);
        $view->set("domains", $this->target());
    }
    
    /**
     * @before _secure, advertiserLayout
     */
    public function create() {
        $this->seo(array("title" => "Create Content", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        $view->set("errors", array());
        if (RequestMethods::post("action") == "content") {
            if (RequestMethods::post("image_url")) {
                $image = $this->urls3upload(RequestMethods::post("image_url"));
            } else {
                $image = $this->s3upload("image", "images");
            }
            $item = new Item(array(
                "user_id" => $this->user->id,
                "model" =>  RequestMethods::post("model", "rpm"),
                "url" =>  RequestMethods::post("url"),
                "title" => RequestMethods::post("title"),
                "image" => $image,
                "commission" => 15,
                "budget" => RequestMethods::post("budget", 5000),
                "category" => implode(",", RequestMethods::post("category", "")),
                "description" => RequestMethods::post("description", ""),
                "live" => 0
                
            ));
            if ($item->validate()) {
                $item->save();
                $rpm = new RPM(array(
                    "item_id" => $item->id,
                    "value" => json_encode(RequestMethods::post("rpm")),
                ));
                $rpm->save();

                $view->set("message", "Campaign Created Successfully now we will approve it within 24 hours and notify you");
            }  else {
                $view->set("errors", $item->getErrors());
            }
        }
    }

    /**
     * @before _secure, advertiserLayout
     */
    public function fetch() {
        $this->seo(array("title" => "Create Content", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        $view->set("errors", array());
        if (RequestMethods::post("action") == "prefetch") {
            $view->set("meta", $this->_bot(RequestMethods::post("url")));
        }
    }

    protected function _bot($url) {
        $bot = new Bot(['cloud' => $url]);
        $bot->execute();
        $doc = array_shift($bot->getDocuments());
        $data["title"] = $doc->query("/html/head/title")->item(0)->nodeValue;
        $data["url"] = $url;

        $metas = $doc->query("/html/head/meta");
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            
            if($meta->getAttribute('name') == 'description') {
                $data["description"] = $meta->getAttribute('content');
            }

            if($meta->getAttribute('property') == 'og:image') {
                $data["image"] = $meta->getAttribute('content');
            }
        }

        return $data;
    }
    
    /**
     * @before _secure, changeLayout, _admin
     */
    public function all() {
        $this->seo(array("title" => "Manage Campaign", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        
        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);

        if (in_array($property, array("url", "title", "category"))) {
            $where = array(
                "{$property} LIKE ?" => "%{$value}%"
            );
        } else {
            $where = array("{$property} = ?" => $value);
        }

        $contents = Item::all($where, array("id", "title", "created", "image", "url", "live", "user_id"), "created", "desc", $limit, $page);
        $count = Item::count($where);

        $view->set("contents", $contents);
        $view->set("property", $property);
        $view->set("value", $value);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
    }

    /**
     * @before _secure, advertiserLayout
     */
    public function manage() {
        $this->seo(array("title" => "Manage Campaign", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        
        $title = RequestMethods::get("title", "");
        
        $where = array(
            "title LIKE ?" => "%{$title}%",
            "user_id = ?" => $this->user->id
        );
        
        $items = Item::all($where, array("id", "title", "created", "image", "url", "live", "commission"), "created", "desc", $limit, $page);
        $count = Item::count($where);

        $view->set("items", $items);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
    }
    
    /**
     * @before _secure, changeLayout, _admin
     */
    public function edit($id = NULL) {
        $this->seo(array("title" => "Edit Content", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $item = Item::first(array("id = ?" => $id));
        $rpm = RPM::first(array("item_id = ?" => $item->id));

        $rpms = array();
        foreach (json_decode($rpm->value, true) as $key => $value) {
            array_push($rpms, array(
                "country" => $key,
                "value" => $value
            ));
        }
        
        if (RequestMethods::post("action") == "update") {
            $item->title = RequestMethods::post("title");
            $item->url = RequestMethods::post("url");
            $item->commission = RequestMethods::post("commission");
            $item->category = implode(",", RequestMethods::post("category"));
            $item->description = RequestMethods::post("description");
            $item->live = RequestMethods::post("live", "0");
            $item->save();

            $rpm->value = json_encode(RequestMethods::post("rpm"));
            $rpm->save();

            $view->set("success", true);
            $view->set("errors", $item->getErrors());
        }
        $view->set("item", $item);
        $view->set("rpms", $rpms);
        $view->set("categories", explode(",", $item->category));
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function delete($id = NULL) {
        $this->noview();
        $urls = Registry::get("MongoDB")->urls;
        $clicks = Registry::get("MongoDB")->clicks;
        $item = Item::first(array("id = ?" => $id));
        if (isset($item)) {
            $links = Link::all(array("item_id = ?" => $item->id));
            foreach ($links as $link) {
                $stat = Stat::all(array("link_id = ?" => $link->id));
                $stat->delete();
                $link->delete();
            }
            $urls->remove(array('item_id' => $item->id));

            $stats = Stat::all(array("item_id = ?" => $item->id));
            foreach ($stats as $stat) {
                $stat->delete();
            }
            $clicks->remove(array('item_id' => $item->id));

            $model = $item->model;

            $campaign_models = $model::all(array("item_id = ?" => $item->id));
            foreach ($campaign_models as $cm) {
                $cm->delete();
            }

            $item->delete();
        }
        $this->redirect($_SERVER["HTTP_REFERER"]);        
    }

    public function resize($image, $width = 560, $height = 292) {
        $path = APP_PATH . "/public/assets/uploads/images";$cdn = CLOUDFRONT;
        $image = base64_decode($image);
        if ($image) {
            $filename = pathinfo($image, PATHINFO_FILENAME);
            $extension = pathinfo($image, PATHINFO_EXTENSION);

            if ($filename && $extension) {
                $thumbnail = "{$filename}-{$width}x{$height}.{$extension}";
                if (!file_exists("{$path}/{$thumbnail}")) {
                    $imagine = new \Imagine\Gd\Imagine();
                    $size = new \Imagine\Image\Box($width, $height);
                    $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
                    $imagine->open("{$path}/{$image}")->thumbnail($size, $mode)->save("{$path}/resize/{$thumbnail}");

                    $s3 = $this->_s3();

                    $string = file_get_contents("{$path}/resize/{$thumbnail}");
                    $result = $s3->putObject([
                        'Bucket' => 's3.clicks99.com',
                        'Key' => 'images/resize/' . $thumbnail,
                        'Body' => $string
                    ]);
                }
                $this->redirect("{$cdn}images/resize/{$thumbnail}");
            }
        } else {
            $this->redirect("{$cdn}img/logo.png");
        }
    }
}
