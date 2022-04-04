<?php
/**
 * YML generator for Horoshop.ua
 */

require_once __DIR__ . '/config.php';

if(php_sapi_name() == 'cli') {
    $arguments = getopt("",array(
        "x_limit:",
        "x_cat_limit:",
        "x_lang:",
        "x_pretty:",
        "x_baseurl:",
        "x_product_description_custom:",
        "x_product_id:",
        "x_ocver:",
    ));
    $XML_KEY=true;
    $base_url = 'https://horoshop.ua';
} else {
    $arguments = $_REQUEST;
    if(isset($_GET[XML_KEY])) {$XML_KEY=true;} else {$XML_KEY=false;}
    $base_url = sprintf(
        "%s://%s",
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
        $_SERVER['SERVER_NAME']
    );
    if(isset($_GET[web_admin])) {
?>
<html>
<head>
</head>
<body>
    <h1>Налаштування:</h1>
<!--     private $x_limit = 10; //Ограничение в количестве товаров (для отладки, чтоб быстрее работало) 0 - без лимита
     public $x_pretty = 1; //Красивое форматирование XML - Человекочитабельный формат или в одну строку (в одну строку быстрее грузится)
     public $x_ocver = 3; //Версия опенкарт 2 или 3
     private $x_product_id; //id конкретного одного товара (для дебага).-->
<form action="">
    <label for="x_limit">Number of products (0 - all):</label><input type="number" name="x_limit" value=3><br>
    <label for="x_cat_limit">Number of categories (0 - all):</label><input type="number" name="x_cat_limit" value=3><br>
    <label for="x_pretty">Pretty output (or one-line XML)</label><input type="checkbox" name="x_pretty" checked><br>
    <label for="x_product_id">Specific Product ID (for debug only):</label><input type="number" name="x_product_id" value=0><br>
    <label for="x_ocver">Opencart version:</label><select name="x_ocver"><option value="2">2</option><option value="3" selected>3</option></select><br>
<input type="submit" name="XML_KEY">
</form>
</body>
</html>
<?php
    exit;
    }
}

/**
 * Making XML price in Horoshop format (https://horoshop.ua)
 * Class YGenerator
 */
class YGenerator
{

    private $languages = array(); //Массив языков, которые используются на сайте;
    private $active_languages;  //Список активных языков
    public  $base_url;          //URL сайта, базовый для ссылок и картинок

    private $x_lang = 0;  //Язык по умолчанчию (0, чтобы проигнорировать)
    private $x_limit = 10; //Ограничение в количестве товаров (для отладки, чтоб быстрее работало)
    private $x_cat_limit = 0; //Ограничение в количестве категорий (для отладки, чтоб быстрее работало)
    public $x_pretty = 1; //Красивое форматирование XML - Человекочитабельный формат или в одну строку
    public $x_ocver = 3; //Версия опенкарт 2 или 3
    public $x_product_description_custom = 0; //Выводить ли кастомные поля из oc_product_description автоматом ?
    private $x_product_id; //id конкретного товара (для дебага). TODO: Перечисление через запятую товаров, если нужны конкретные id шники

    public function __construct($arguments) {
        //?? is php7+ dependend function. May fail on ancient php5.x installations
        //in this case should be rewriten to isset() function
   
        foreach($arguments as $key=>$value) {
            $this->$key = (int)$value;
        }
            //ОПЦИИ
    
    //Выводить снятые с публикации
    //Выводить без картинок
    //Выводить с нулевым наличием
    //Выводить без названия

    /*Что делать если:
    - только один язык
    + если присущи языки, которые не активны или которых нет в списке
    */

    }

    /**
     * Building YML
     * @return SimpleXMLElement
     */
    function getYml()
    {
        require_once __DIR__ . '/config.php';
        $con = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

        if (!$con) {
            echo "Error: Unable to connect to MySQL." . PHP_EOL;
            echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
            echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
            die();
        }

        mysqli_set_charset($con, "utf8");

        $this->languages = $this->getLanguages($con);
        $this->active_languages = array_keys($this->languages);

        $xml = new SimpleXMLExtended("<?xml version=\"1.0\" encoding=\"UTF-8\"?><hcatalog/>");
        $dt = date("Y-m-d");
        $tm = date("H:i");
        $xml->addAttribute("date", $dt . ' ' . $tm);


        $shop = $xml->addChild('hshop');
        $shop->addChild('name', "Horoshop-Export");
        $shop->addChild('company', "Horoshop");
        $shop->addChild('url', "https://www.horoshop.ua/");
        $shop->addChild('version', "1.0.1");

        $currencies = $shop->addChild('currencies');
        $currency = $currencies->addChild('currency');
        $currency->addAttribute("id", "UAH");
        $currency->addAttribute("rate", "1");

        //show service info
        $languages = $shop->addChild('languages');
        foreach($this->languages as $key=>$value) {
          $language = $languages->addChild('language', $value['name']);
          $language->addAttribute("id", $key);
          $language->addAttribute("name", $value['name']);
          $language->addAttribute("code", $value['code']);
        }

        // #### Categories Section ####
        $categories = $shop->addChild('categories');
        $sql = "SELECT * FROM `oc_category` WHERE 1";
        $sql .= ' AND status = 1';
        $sql .= ' ORDER BY `category_id`';
        if($this->x_cat_limit) { $sql .= " LIMIT $this->x_cat_limit"; }

        $result2 = $con->query($sql);
        if ($result2->num_rows > 0) {
            while ($row2 = $result2->fetch_assoc()) {
                $sql = "SELECT * FROM `oc_category_description` WHERE category_id = '" . $row2['category_id'] . "'";
                $sql .= ' AND language_id IN(' . implode(',',$this->active_languages) . ')';
                $result = $con->query($sql);
                if ($result->num_rows > 0) {
                    $category = $categories->addChild('category'); //echo $row['name'] . PHP_EOL;
                    $category->addAttribute("id", $row2['category_id']);
                    $category->addChild("sort_order", $row2['sort_order']);
                    $category->addChild("top", $row2['top']);
                    $category->addChild("image", $this->base_url . $row2['image']);
                    // $category->addChild("url", $this->base_url . '/index.php?route=product/category&amp;category_id=' . $row2['category_id']);
                    $category->addChild("url", '' . $this->get_oc_url_alias($con, 'category', $row2['category_id'], $this->x_ocver));
                    
                    $parentId = $this->getParentIdCategory($con, $row2['category_id']);
                    if ($parentId != 0) {
                        $category->addAttribute("parentId", $parentId);
                    }
                    while ($row = $result->fetch_assoc()) {
                        $language = $category->addChild("language");
                        $language->addAttribute("id", $row['language_id']);
                        $language->addChild("name", htmlspecialchars($row['name']));
                        $language->addChildWithCDATA('seo_description', html_entity_decode($row['description']));
                        $language->addChild("meta_title", htmlspecialchars($row['meta_title']));
                        $language->addChild("meta_keyword", htmlspecialchars($row['meta_keyword']));
                        $language->addChild("meta_description", htmlspecialchars($row['meta_description']));
                        $language->addChild("h1", htmlspecialchars($row['h1']));
                    }
                }
            }
        }
        //#### End Categories Section ####

        // #### Offers Section ####
        $offers = $shop->addChild('offers');
        //$sql = "SELECT * FROM  `oc_product` WHERE `quantity` > 0";
        $sql = "SELECT * FROM  `oc_product`";
        if($this->x_product_id) { $sql .= " WHERE product_id = $this->x_product_id"; }
        if($this->x_limit) { $sql .= " LIMIT $this->x_limit"; }
        $result = $con->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $productId = $row['product_id'];
                $manufacturerId = $row['manufacturer_id'];
                $vendorName = $this->getVendorName($con, $manufacturerId);
                $stock_quantity = $row['quantity'];
                $price = $row['price'];
                $article = $row['model'];
                $vendorCode = $row['sku'];
                $img = $this->base_url . '/image/' . $row['image'];

                //Multiple pictures section
                $sql3 = "SELECT * FROM `oc_product_image` WHERE `product_id` = '$productId' ORDER BY sort_order ASC";
                $result3 = $con->query($sql3);
                $images=array();
                if ($result3->num_rows > 0) {
                    while ($row3 = $result3->fetch_assoc()) {
                    $images[] = $this->base_url . '/image/' . $row3['image'];
                    }
                }

                // #### Attribute section ####
                $listAttributes = array();
                $sql3 = "SELECT * FROM `oc_product_attribute` WHERE `product_id` = '$productId'";
                if($this->x_lang) { $sql3 .= " AND language_id = $this->x_lang"; }
                $result3 = $con->query($sql3);
//                if ($result3->num_rows > 0) { //Не выгружать без атрибутов
                    while ($row3 = $result3->fetch_assoc()) {
                        $data = array();
                        $nameAttribute = $this->getNameAttributeById($con, $row3['attribute_id'], $row3['language_id']);
                        $data['nameAttribute'] = $nameAttribute;
                        $data['attribute_id'] = $row3['attribute_id'];
                        $valueAttribute = htmlspecialchars($row3['text']);
                        $data['valueAttribute'] = $valueAttribute;
                        $data['sortOrder'] = $this->getAttributeSortOrder($con, $row3['attribute_id']);
                        $data['language_id'] = $row3['language_id'];

                        array_push($listAttributes, $data);
                    }

                    //checking, how many attributes in product && if exist image of products
                    //don't adding the product if min attributes
//////                    if (count($listAttributes) > 4 && strlen($row['image']) > 4) {
                        // $textUrl = $this->base_url . '/index.php?route=product/product&amp;product_id=' . $productId;
                        $textUrl = '' . $this->get_oc_url_alias($con, 'product', $productId, $this->x_ocver);
                        $category = $this->getCategoryOfProduct($con, $productId);

                        $descriptions = array();
                        $sql2 = "SELECT * FROM `oc_product_description` WHERE `product_id` = '$productId'";
                        if($this->x_lang) { $sql2 .= " AND language_id = $this->x_lang"; }
                        $result2 = $con->query($sql2);
                        if ($result2->num_rows > 0) {
                            while ($row2 = $result2->fetch_assoc()) {
                                //$text = $this->removeTags($row2['description']);
                                $data = array();
                                foreach($row2 as $key=>$value) {
                                    $data[$key] = htmlspecialchars($value);
                                }
                                array_push($descriptions, $data);
                            }
                        }

//                    sort array by `sortOrder`
                        for ($i = 0; $i < count($listAttributes); $i++) {
                            for ($j = $i + 1; $j < count($listAttributes); $j++) {
                                if ($listAttributes[$i]['sortOrder'] > $listAttributes[$j]['sortOrder']) {
                                    $temp = $listAttributes[$j];
                                    $listAttributes[$j] = $listAttributes[$i];
                                    $listAttributes[$i] = $temp;
                                }
                            }
                        }
///END of Variables
                        $alloptions = $this->getAllProductOptions($con, $productId);
                        $alloptions_sorted = array();

                        foreach ($alloptions as $key => $item) {
                        $alloptions_sorted[$item['option_value_id']][$key] = $item;
                        }

                        ksort($alloptions_sorted, SORT_NUMERIC);
                        // var_dump($alloptions_sorted); die();                        

                        //OPTIONS
                        // var_dump($alloptions); die();
                        //if($alloptions) { $offer->addChild('options', var_export($alloptions, true)); }
                        if($alloptions) {
                            foreach($alloptions_sorted as $alloptions) {
                                $offer = $offers->addChild('offer');
                                $offer->addAttribute("group_id", $productId);

                                $i = 0;    
                                foreach($alloptions as $option_values) {
                                        if(!$i) {
                                            $offer->addAttribute("id", $option_values['artikul']);
                                            $price = $option_values['price'];
                                            $article = $option_values['artikul'];
                                            $vendorCode = $option_values['barcode'];
                                        }
                                        $i++;
                                        $option = $offer->addChild('param', $option_values['name']);
                                        $option->addAttribute('name', $option_values['option_name']);
                                        $option->addAttribute('id', $option_values['option_id']);
                                        $option->addAttribute('value_id', $option_values['option_value_id']);
                                        $option->addAttribute('type', 'modification');
                                        $option->addAttribute('langid', $option_values['language_id']);


                                        /*$option->addChild('name', $option_values['name']);
                                        $option->addChild('price', $option_values['price']);
                                        $option->addChild('artikul', $option_values['artikul']);
                                        $option->addChild('barcode', $option_values['barcode']);
                                        $option->addChild('image', $option_values['image']);*/
                                        // $option->addChild(str_replace('1c', 'one_c', $key), htmlspecialchars($value));

                                    }                                

                        /* HERE SHOULD START A MACRO */
                        /*****************************/
                        $offer->addAttribute("available", "true");
                        $offer->addChild('article', $article);
                        $offer->addChild('vendorCode', $vendorCode);
                        $offer->addChild('alias', $textUrl);
                        $offer->addChild('price', $price);
                        $offer->addChild('currencyId', 'UAH');
                        $offer->addChild('categoryId', $category);
                        if($img) {
                            $offer->addChild('picture', $img);
                        }

                        if(isset($images)) {
                            foreach($images as $value) {
                                $offer->addChild('picture', $value);
                            }
                        }
 
                        $offer->addChild('vendor', $vendorName);
                        $offer->addChild('stock_quantity', $stock_quantity);
                        //$offer->addChild('store', "false");
                        //$offer->addChild('pickup', "false");
                        //$offer->addChild('delivery', "false")
                        foreach ($descriptions as $description) {
                            //extract($description);

                                unset($description['product_id']);

                                $text = $description['description'];
                                unset($description['description']);
                                $langid = $description['language_id'];
                                unset($description['language_id']);
                                $name = $description['name'];
                                unset($description['name']);

                            $o_name = $offer->addChild('name', $name);
                            $o_name->addAttribute('langid', $langid);
                            $o_description = $offer->addChildWithCDATA('description', $text);
                            $o_description->addAttribute('langid', $langid);

                            $temp = $offer->addChild("meta_title", $description['meta_title']);
                            $temp->addAttribute('langid', $langid);
                            unset($description['meta_title']);
                            $temp = $offer->addChild("meta_keyword", $description['meta_keyword']);
                            $temp->addAttribute('langid', $langid);
                            unset($description['meta_keyword']);
                            $temp = $offer->addChildWithCDATA("meta_description", $description['meta_description']);
                            $temp->addAttribute('langid', $langid);
                            unset($description['meta_description']);
                            $temp = $offer->addChild("h1", $description['meta_h1']);
                            $temp->addAttribute('langid', $langid);
                            unset($description['meta_h1']);

                            if($this->x_product_description_custom) {
                                foreach($description as $key=>$value) {
                                    $temp = $offer->addChildWithCDATA($key, $value);
                                    $temp->addAttribute('langid', $langid);
                                    $temp->addAttribute('type', 'custom');
                                }
                            }
                        }
                        ### Adding attributes
                        for ($i = 0; $i < count($listAttributes); $i++) {
                            $valueAttribute = trim($listAttributes[$i]['valueAttribute']);
                            $valueAttribute = $this->cutExtraCharacters($valueAttribute);
                            $param = $offer->addChild('param', $valueAttribute);
                            $param->addAttribute('name', $listAttributes[$i]['nameAttribute']);
                            $param->addAttribute('id', $listAttributes[$i]['attribute_id']);
                            $param->addAttribute('langid', $listAttributes[$i]['language_id']);
                        }
                        /*****************************/
                        /* //HERE SHOULD STOP A MACRO */

                            }
                        } else {
                            $offer = $offers->addChild('offer');
                            $offer->addAttribute("id", $productId);
                        /* HERE SHOULD START A MACRO */
                        /*****************************/
                        $offer->addAttribute("available", "true");
                        $offer->addChild('article', $article);
                        $offer->addChild('vendorCode', $vendorCode);
                        $offer->addChild('alias', $textUrl);
                        $offer->addChild('price', $price);
                        $offer->addChild('currencyId', 'UAH');
                        $offer->addChild('categoryId', $category);
                        if($img) {
                            $offer->addChild('picture', $img);
                        }

                        if(isset($images)) {
                            foreach($images as $value) {
                                $offer->addChild('picture', $value);
                            }
                        }
 
                        $offer->addChild('vendor', $vendorName);
                        $offer->addChild('stock_quantity', $stock_quantity);
                        //$offer->addChild('store', "false");
                        //$offer->addChild('pickup', "false");
                        //$offer->addChild('delivery', "false")
                        foreach ($descriptions as $description) {
                            // extract($description);

                                unset($description['product_id']);

                                $text = $description['description'];
                                unset($description['description']);
                                $langid = $description['language_id'];
                                unset($description['language_id']);
                                $name = $description['name'];
                                unset($description['name']);

                            $o_name = $offer->addChild('name', $name);
                            $o_name->addAttribute('langid', $langid);
                            $o_description = $offer->addChildWithCDATA('description', $text);
                            $o_description->addAttribute('langid', $langid);

                            $temp = $offer->addChild("meta_title", $description['meta_title']);
                            $temp->addAttribute('langid', $langid);
                            unset($description['meta_title']);
                            $temp = $offer->addChild("meta_keyword", $description['meta_keyword']);
                            $temp->addAttribute('langid', $langid);
                            unset($description['meta_keyword']);
                            $temp = $offer->addChildWithCDATA("meta_description", $description['meta_description']);
                            $temp->addAttribute('langid', $langid);
                            unset($description['meta_description']);
                            $temp = $offer->addChild("h1", $description['meta_h1']);
                            $temp->addAttribute('langid', $langid);
                            unset($description['meta_h1']);

                            if($this->x_product_description_custom) {
                                foreach($description as $key=>$value) {
                                    $temp = $offer->addChildWithCDATA($key, $value);
                                    $temp->addAttribute('langid', $langid);
                                    $temp->addAttribute('type', 'custom');
                                }
                            }
                        }
                        ### Adding attributes
                        for ($i = 0; $i < count($listAttributes); $i++) {
                            $valueAttribute = trim($listAttributes[$i]['valueAttribute']);
                            $valueAttribute = $this->cutExtraCharacters($valueAttribute);
                            $param = $offer->addChild('param', $valueAttribute);
                            $param->addAttribute('name', $listAttributes[$i]['nameAttribute']);
                            $param->addAttribute('id', $listAttributes[$i]['attribute_id']);
                            $param->addAttribute('langid', $listAttributes[$i]['language_id']);
                        }
                        /*****************************/
                        /* //HERE SHOULD STOP A MACRO */
                        }

                   /////// }
                //}
            }
        }
        return $xml;
    }

    /**
     * @param $str
     * @return mixed
     */
    private function cutExtraCharacters($str){
        $cyr = [
            ' кг', ' л', ' Вт', ' куб. м/ч', ' см', ' дБ'
        ];

        //$str = str_replace($cyr, '', $str);
        return $str;
    }


    /**
     * Getting attribute sort order by id
     * @param $con
     * @param $attributeId
     * @return int
     */
    private function getAttributeSortOrder($con, $attributeId)
    {
        $sql = "SELECT `sort_order` FROM `oc_attribute` WHERE `attribute_id` = '$attributeId'";
        $result = $con->query($sql);
        $sortOrder = 0;
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $sortOrder = $row['sort_order'];
            }
        }
        if ($sortOrder == 0) $sortOrder = 50;
        return $sortOrder;
    }

    /**
     * Getting name of the attribute by id
     * @param $con
     * @param $attributeId
     * @return string
     */
    private function getNameAttributeById($con, $attributeId, $language_id)
    {
        $sql = "SELECT `name` FROM `oc_attribute_description` WHERE `attribute_id` = '$attributeId'";
        $sql .= " AND language_id = $language_id";
        $result = $con->query($sql);
        $name = '';
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $name = $row['name'];
            }
        }
        return $name;
    }

    /**
     * Removing HTML tags and other garbage
     * @param $str
     * @return string
     */
    private function removeTags($str)
    {
        $str = preg_replace('/<meta([^&]*)\/>/', '', $str);
        $str = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $str);
        $str = preg_replace('/<[^>]*>/', '', $str);
        $str = str_replace('P.S. В случае отсутствия товара, оставьте заявку на нашем сайте!', '', $str);
        $str = str_replace('Характеристики и комплектация товара могут изменяться производителем без уведомления', '', $str);
//    $str = $str.replaceAll("<[^>]*>", "");
        return $str;
    }

    /**
     * Getting ID of category by ID of the product
     * @param $con
     * @param $productId
     * @return int
     */
    private function getCategoryOfProduct($con, $productId)
    {
        $sql = "SELECT `category_id` FROM `oc_product_to_category` WHERE `product_id` = '$productId'";
        $result = $con->query($sql);
        $categoryId = 0;
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $categoryId = $row['category_id'];
            }
        }
        return $categoryId;
    }

    /**
     * Getting ID of parent category by ID of the current category
     * @param $con
     * @param $codeGroup
     * @return int
     */
    private function getParentIdCategory($con, $codeGroup)
    {
        $sql = "SELECT `parent_id` FROM `oc_category` WHERE `category_id` = '$codeGroup'";
        $result = $con->query($sql);
        $parentId = 0;
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $parentId = $row['parent_id'];
            }
        }
        return $parentId;
    }

    /**
     * Getting name of Vendor of the product by manufacturer Id
     * @param $con
     * @param $manufacturerId
     * @return string
     */
    private function getVendorName($con, $manufacturerId)
    {
        $sql = "SELECT `name` FROM  `oc_manufacturer` WHERE `manufacturer_id` = '$manufacturerId'";
        $result = $con->query($sql);
        $nameVendor = '';
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $nameVendor = $row['name'];
            }
        }
        return htmlspecialchars($nameVendor);
    }

    /**
     * Getting list of avaliable languages
     * @return array
     */
    private function getLanguages($con)
    {
        $sql = "SELECT * FROM oc_language WHERE `status` = '1'";
        $result = $con->query($sql);
        $languages = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $languages[$row['language_id']]['name'] = $row['name'];
                $languages[$row['language_id']]['code'] = $row['code'];
            }
        }
        return $languages;
    }

        //Unused function
        public function getProductOptions($option_ids, $product_id) {
                $lang = (int)$this->x_lang;

                $sql = ("SELECT pov.*, od.name AS option_name, ovd.name, ov.image
                        FROM " . DB_PREFIX . "product_option_value pov
                        LEFT JOIN " . DB_PREFIX . "option_value ov ON (ov.option_value_id = pov.option_value_id)
                        LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id)
                        LEFT JOIN " . DB_PREFIX . "option_description od ON (od.option_id = pov.option_id) AND (od.language_id = '$lang')
                        WHERE pov.option_id IN (". implode(',', array_map('intval', $option_ids)) .") AND pov.product_id = '". (int)$product_id."'
                                AND ovd.language_id = '$lang'");
                $result = $con->query($sql);
                return $result->fetch_all(MYSQLI_ASSOC);
        }

        public function getAllProductOptions($con, $product_id) {
                $lang = (int)$this->x_lang;
                if($lang) {
                    $sql = ("SELECT pov.*, od.name AS option_name, ovd.name, ov.image
                        FROM " . DB_PREFIX . "product_option_value pov
                        LEFT JOIN " . DB_PREFIX . "option_value ov ON (ov.option_value_id = pov.option_value_id)
                        LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id)
                        LEFT JOIN " . DB_PREFIX . "option_description od ON (od.option_id = pov.option_id) AND (od.language_id = '$lang')
                        WHERE pov.product_id = '". (int)$product_id."'
                                AND ovd.language_id = '$lang'");
                } else {
                    $sql = ("SELECT pov.*, od.name AS option_name, ovd.name, ov.image, ovd.language_id
                        FROM " . DB_PREFIX . "product_option_value pov
                        LEFT JOIN " . DB_PREFIX . "option_value ov ON (ov.option_value_id = pov.option_value_id)
                        LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id)
                        LEFT JOIN " . DB_PREFIX . "option_description od ON (od.option_id = pov.option_id) AND (od.language_id = ovd.language_id)
                        WHERE pov.product_id = '". (int)$product_id. "'");
                }
                $result = $con->query($sql);
                return $result->fetch_all(MYSQLI_ASSOC);
        }
        private function get_oc_url_alias($con, $type, $id, $ocver = 3, $lang = 0) {
            if($ocver == 3) {
                $query = $type . '_id=' . $id;
                $sql = "SELECT * FROM oc_seo_url WHERE query='$query'";
                $result = $con->query($sql);
                $keyword = '';
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $keyword = $row['keyword'];
                    }
                }
            } else if($ocver == 2) {
                $query = $type . '_id=' . $id;
                $sql = "SELECT * FROM oc_url_alias WHERE query='$query'";
                $result = $con->query($sql);
                $keyword = '';
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $keyword = $row['keyword'];
                    }
                }
             } else {
                $keyword = 'nope';
            }
        return $keyword;
        }
}

// http://coffeerings.posterous.com/php-simplexml-and-cdata
class SimpleXMLExtended extends SimpleXMLElement {
  public function addCData($cdata_text) {
    $node = dom_import_simplexml($this); 
    $no   = $node->ownerDocument; 
    $node->appendChild($no->createCDATASection($cdata_text)); 
  }
  
   /**
   * Adds a child with $value inside CDATA
   * @param unknown $name
   * @param unknown $value
   */
  public function addChildWithCDATA($name, $value = NULL) {
    $new_child = $this->addChild($name);

    if ($new_child !== NULL) {
      $node = dom_import_simplexml($new_child);
      $no   = $node->ownerDocument;
      $node->appendChild($no->createCDATASection($value));
    }

    return $new_child;
  } 
}

if($XML_KEY) {
    date_default_timezone_set('Europe/Kiev');
    $yGenerator = new YGenerator($arguments);
    if(isset($yGenerator->x_baseurl)) {
        $yGenerator->base_url = $arguments['x_baseurl'];
    } else {
        $yGenerator->base_url = $base_url;
    }

    $xml = $yGenerator->getYml();
    Header('Content-type: text/xml');

    if($yGenerator->x_pretty) {
      $doc = new DOMDocument();
      $doc->preserveWhiteSpace = false;
      $doc->formatOutput = true;
      $doc->loadXML($xml->asXML());
      echo $doc->saveXML();
    } else {
      print($xml->asXML());
    }

}else echo '-= access denied =-';