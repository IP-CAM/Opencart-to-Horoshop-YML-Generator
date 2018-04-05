<?php
/**
 * YML generator for sponsor.net.ua
 */
 
require_once __DIR__ . '/db_config.php';

if(isset($_GET[XML_KEY])) {
    $yGenerator = new YGenerator();
    $xml = $yGenerator->getYml();
    Header('Content-type: text/xml');
    print($xml->asXML());
}else echo '-= fuck off =-';

/**
 * Making YML layout depending Rozetka's pattern (rozetka.com.ua/sellerinfo/pricelist/)
 * Class YGenerator
 */
class YGenerator
{

    /**
     * Building YML
     * @return SimpleXMLElement
     */
    function getYml()
    {
        require_once __DIR__ . '/db_connect.php';

        $db = new DB_CONNECT();
        $con = $db->connect();

        $xml = new SimpleXMLElement('<yml_catalog/>');
        $dt = date("Y-m-d");
        $tm = date("H:i");
        $xml->addAttribute("date", $dt . ' ' . $tm);


        $shop = $xml->addChild('shop');
        $shop->addChild('name', "Technika-Sale");
        $shop->addChild('company', "Technika-Sale");
        $shop->addChild('url', "http://www.sponsor.net.ua/");
        $shop->addChild('version', "2.3.0.2.3");

        $currencies = $shop->addChild('currencies');
        $currency = $currencies->addChild('currency');
        $currency->addAttribute("id", "UAH");
        $currency->addAttribute("rate", "1");

        // #### Categories Section ####
        $categories = $shop->addChild('categories');
        $sql = "SELECT `category_id`, `name` FROM `op_category_description` WHERE 1 ORDER BY `category_id`";
        $result = $con->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $category = $categories->addChild('category', $row['name']);
                $category->addAttribute("id", $row['category_id']);
                $parentId = $this->getParentIdCategory($con, $row['category_id']);
                if ($parentId != 0) {
                    $category->addAttribute("parentId", $parentId);
                }
            }
        }
        //#### End Categories Section ####

        // #### Offers Section ####
        $offers = $shop->addChild('offers');
        $sql = "SELECT * FROM  `op_product` WHERE `quantity` > 0";
        $result = $con->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $productId = $row['product_id'];
                $manufacturerId = $row['manufacturer_id'];
                $stock_quantity = $row['quantity'];

                // #### Attribute section ####
                $listAttributes = array();
                $sql3 = "SELECT * FROM `op_product_attribute` WHERE `product_id` = '$productId'";
                $result3 = $con->query($sql3);
                if ($result3->num_rows > 0) {
                    while ($row3 = $result3->fetch_assoc()) {
                        $data = array();
                        $nameAttribute = $this->getNameAttributeById($con, $row3['attribute_id']);
                        $data['nameAttribute'] = $nameAttribute;
                        $valueAttribute = $row3['text'];
                        $data['valueAttribute'] = $valueAttribute;
                        $data['sortOrder'] = $this->getAttributeSortOrder($con, $row3['attribute_id']);

                        array_push($listAttributes, $data);
                    }

                    //checking, how many attributes in product && if exist image of products
                    //don't adding the product if min attributes
                    if (count($listAttributes) > 4 && strlen($row['image']) > 4) {
                        $offer = $offers->addChild('offer');
                        $offer->addAttribute("id", $row['product_id']);
                        $offer->addAttribute("available", "true");
                        $textUrl = 'http://www.sponsor.net.ua/index.php?route=product/product&amp;product_id=' . $row['product_id'];
                        $offer->addChild('url', $textUrl);
                        $offer->addChild('price', $row['price']);
                        $offer->addChild('currencyId', 'UAH');
                        $offer->addChild('categoryId', $this->getCategoryOfProduct($con, $row['product_id']));
                        $img = 'http://sponsor.net.ua/image/' . $row['image'];
                        $offer->addChild('picture', $img);
                        $vendorName = $this->getVendorName($con, $manufacturerId);
                        $offer->addChild('vendor', $vendorName);
                        $offer->addChild('stock_quantity', $stock_quantity);
                        $offer->addChild('store', "false");
                        $offer->addChild('pickup', "false");
                        $offer->addChild('delivery', "false");

                        $sql2 = "SELECT * FROM `op_product_description` WHERE `product_id` = '$productId'";
                        $result2 = $con->query($sql2);
                        if ($result2->num_rows > 0) {
                            while ($row2 = $result2->fetch_assoc()) {
                                $text = $this->removeTags($row2['description']);
                                $name = $offer->addChild('name', $row2['name']);
                                if (strlen(trim($text)) == 0) {
                                    $offer->addChild('description', $name);
                                } else {
                                    $offer->addChild('description', $text);
                                }
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

                        ### Adding attributes
                        for ($i = 0; $i < count($listAttributes); $i++) {
                            $valueAttribute = trim($listAttributes[$i]['valueAttribute']);
                            $valueAttribute = $this->cutExtraCharacters($valueAttribute);
                            $param = $offer->addChild('param', $valueAttribute);
                            $param->addAttribute('name', $listAttributes[$i]['nameAttribute']);
                        }
                    }
                }
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

        $str = str_replace($cyr, '', $str);
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
        $sql = "SELECT `sort_order` FROM `op_attribute` WHERE `attribute_id` = '$attributeId'";
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
    private function getNameAttributeById($con, $attributeId)
    {
        $sql = "SELECT `name` FROM `op_attribute_description` WHERE `attribute_id` = '$attributeId'";
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
        $sql = "SELECT `category_id` FROM `op_product_to_category` WHERE `product_id` = '$productId'";
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
        $sql = "SELECT `parent_id` FROM `op_category` WHERE `category_id` = '$codeGroup'";
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
        $sql = "SELECT `name` FROM  `op_manufacturer` WHERE `manufacturer_id` = '$manufacturerId'";
        $result = $con->query($sql);
        $nameVendor = '';
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $nameVendor = $row['name'];
            }
        }
        return $nameVendor;
    }
}