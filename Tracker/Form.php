<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker;

class Form
{
    private static $save_button = 'Save changes';
    private static $form_fields = array();
    private static $init = null;

    const defFields = array(
        'title' => '',
        'type' => 'text',
        'default' => '',
        'description' => '',
        'holder' => '',
        'options' => array(
            array('value' => 0, 'label' => "Disable"),
            array('value' => 1, 'label' => "Enable")
        )
    );

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }

        return self::$init;
    }

    public static function formFields($fields)
    {
        foreach ($fields as $key=>$value) {
            $fields[$key] = array_merge(self::defFields, $value);
        }

        self::$form_fields = array_merge(self::$form_fields, $fields);

        return self::init();
    }

    public static function initProcess()
    {
        $data = Config::POST(Config::$name);
        if (!empty($data)) {
            $fail = false;
            foreach ($data[Config::$name] as $key=>$value) {
                if (in_array($key, array('tracking_key', 'rest_key', 'customer_id', 'google_tagCode')) && empty($value)) {
                    $fail = $key;
                }

                Config::setValue($key, $value);

                if ($key == 'push_status') {
                    Observer::pushStatus();
                }
            }

            if ($fail) {
                Admin::addNotice(
                    array(
                        'type' => 'error',
                        'message'=> 'Please fill are Require(*) fields'
                    )
                );
            } else {
                Admin::addNotice(
                    array(
                        'message'=>'Your settings have been saved.'
                    )
                );
            }
        }
    }

    public static function getForm($clean = false)
    {
        $out = array();

        $out[] = '<form method="POST" action="" enctype="multipart/form-data">
    <table class="form-table">';

        foreach (self::$form_fields as $key=>$value) {
            $out[] = '        <tr valign="top">
            <th scope="row" class="titledesc">
                <label ' . ($value['type'] != 'title' ? ' for="'.Config::$name.'_'.$key.'"' : '') . '>'.$value['title'].'</label>
            </th>
            <td class="forminp">
                <fieldset>';

            $value['default'] = ($value['default'] !== '' ? $value['default'] : Config::getValue($key));

            switch ($value['type']) {
                case 'title':

                    break;
                case 'select':

                    $out[] = '<select style="width: 100%;max-width: 20rem;"
                        name="'.Config::$name.'['.$key.']" id="'.Config::$name.'_'.$key.'">';
                    foreach ($value['options'] as $o) {
                        $out[] = '<option value="'.$o['value'].'" '.($value['default'] == $o['value'] ?
                            'selected="selected" ' : '').'>'.$o['label'].'</option>';
                    }
                    $out[] = '</select>';
                    break;
                default:
                    if (is_array($value['default'])) {
                        $value['default'] = implode('|', $value['default']);
                    }
                    $out[] = '                    <input
                        type="text"
                        style="width: 100%;max-width: 20rem;"
                        name="'.Config::$name.'['.$key.']"
                        id="'.Config::$name.'_'.$key.'"
                        value="'.$value['default'].'" '.(
                        $value['holder'] !== '' ?
                            'placeholder="'.$value['holder'].'" ' : ''
                    ).'/>';
            }


            if ($value['description'] !== '') {
                $out[] = '                    <p class="description">'.$value['description'].'</p>';
            }

            $out[] = '                </fieldset>
            </td>
        </tr>';
        }

        $out[] = '    </table>
    <p class="submit">
	    <input type="hidden" id="'.Config::$name.'" name="'.Config::$name.'[valid]" value="'.Config::$name.'_valid" />
		<button class="button-primary" type="submit" value="'.self::$save_button.'">'.self::$save_button.'</button>
    </p>
</form>';

        if ($clean) {
            self::clean();
        }

        return ent2ncr(implode(PHP_EOL, $out));
    }

    public static function clean()
    {
        self::$form_fields = array();
    }
}
