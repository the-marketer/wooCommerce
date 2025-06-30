<?php
namespace Mktr\Tracker\Helpers;

class Helper
{
    public static function shape_space_allowed_html() {

        $allowed_tags = array(
            'a' => array(
                'class' => array(),
                'href'  => array(),
                'rel'   => array(),
                'title' => array(),
            ),
            'abbr' => array(
                'title' => array(),
            ),
            'b' => array(),
            'blockquote' => array(
                'cite'  => array(),
            ),
            'cite' => array(
                'title' => array(),
            ),
            'code' => array(),
            'del' => array(
                'datetime' => array(),
                'title' => array(),
            ),
            'dd' => array(),
            'div' => array(
                'class' => array(),
                'title' => array(),
                'style' => array(),
            ),
            'dl' => array(),
            'dt' => array(),
            'em' => array(),
            'h1' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'i' => array(),
            'img' => array(
                'alt'    => array(),
                'class'  => array(),
                'height' => array(),
                'src'    => array(),
                'width'  => array(),
            ),
            'li' => array(
                'class' => array(),
            ),
            'ol' => array(
                'class' => array(),
            ),
            'p' => array(
                'class' => array(),
            ),
            'q' => array(
                'cite' => array(),
                'title' => array(),
            ),
            'span' => array(
                'class' => array(),
                'title' => array(),
                'style' => array(),
            ),
            'strike' => array(),
            'strong' => array(),
            'ul' => array(
                'class' => array(),
            ),
            'input' => array(
                'type' => array(),
                'id' => array(),
                'name' => array(),
                'value' => array(),
                'placeholder' => array(),
                'class' => array(),
                'checked' => array(),
                'onchange' => array(),
                'rows' => array(),
                'cols' => array(),
                'style' => array(),
                'hidden' => array(),
                'autocomplete' => array(),
                'disabled' => array(),
                'readonly' => array(),
            ),
            'button' => array(
                'type' => array(),
                'class' => array(),
                'id' => array(),
                'name' => array(),
                'value' => array(),
                'onclick' => array(),
            ),
            'form' => array(
                'method' => array(),
                'action' => array(),
                'enctype' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'textarea' => array(
                'id' => array(),
                'name' => array(),
                'rows' => array(),
                'cols' => array(),
                'placeholder' => array(),
                'class' => array(),
            ),
            'label' => array(
                'for' => array(),
                'class' => array(),
            ),
            'select' => array(
                'id' => array(),
                'name' => array(),
                'class' => array(),
            ),
            'option' => array(
                'value' => array(),
                'selected' => array(),
            ),
        );

        return $allowed_tags;
    }

}