<?php

function ppsfwoo_register_product_type()
{
    if(!class_exists('\WC_Product')) {

        return;

    }
    
    class WC_Product_ppsfwoo extends \WC_Product
    {
        public $product_type;
        
        public function __construct($product)
        {
            $this->product_type = 'ppsfwoo';

            parent::__construct($product);
        }
    }
}
