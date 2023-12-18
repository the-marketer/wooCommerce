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
                if (in_array($key, array('tracking_key', 'rest_key', 'customer_id', 'google_tagCode')) && empty($value)) { $fail = $key; }

                Config::setValue($key, $value);

                if ($key == 'push_status') { Observer::pushStatus(); }
                if ($key == 'opt_in') {
                    $plug = 'mailpoet/mailpoet.php';
                    // $installed = array_key_exists($plug , $installed_plugins ) || in_array($plug, $installed_plugins, true );
                    $active    = is_plugin_active($plug);
                    if ($active) {
                        if (Config::getValue('opt_in_oldmail') === null) {
                            Config::setValue('opt_in_oldmail', \MailPoet\Settings\SettingsController::getInstance()->get('signup_confirmation.enabled'));
                        }
                        if ($value == 1) {
                            \MailPoet\Settings\SettingsController::getInstance()->set('signup_confirmation.enabled', 0);
                        } else {
                            \MailPoet\Settings\SettingsController::getInstance()->set('signup_confirmation.enabled', Config::getValue('opt_in_oldmail'));
                        }
                        $id = Config::getMailPoetId(false);
                        
                        if ( $id !== null ) {
                            $optin_on_checkout = \MailPoet\Settings\SettingsController::getInstance()->get('woocommerce.optin_on_checkout');
                            if ($optin_on_checkout !== null) {
                                if (!array_key_exists('segments', $optin_on_checkout)) {
                                    $optin_on_checkout['segments'] = [ $id ];
                                } else if (!in_array($id, $optin_on_checkout['segments'])) {
                                    $optin_on_checkout['segments'][] = $id;
                                }
                                if ($value == 1) { $optin_on_checkout['enabled'] = 1; }
                            } else if ($value == 1) {
                                $optin_on_checkout = [
                                    'message' => 'I would like to receive exclusive emails with discounts and product information',
                                    'segments' => [ $id ],
                                    'enabled' => 1
                                ];
                            }
                            if ($optin_on_checkout !== null) {
                                \MailPoet\Settings\SettingsController::getInstance()->set('woocommerce.optin_on_checkout', $optin_on_checkout);
                            }

                            $subscribe = \MailPoet\Settings\SettingsController::getInstance()->get('subscribe');
                            $list = ['on_register', 'on_comment'];
                            if ($subscribe !== null) {
                                foreach($list as $k) {
                                    if (array_key_exists($k, $subscribe)) {
                                        if (!array_key_exists('segments', $subscribe[$k])) {
                                            $subscribe[$k]['segments'] = [ $id ];
                                        } else if (!in_array($id, $subscribe[$k]['segments'])) {
                                            $subscribe[$k]['segments'][] = $id;
                                        }
                                        if ($value == 1) { $subscribe[$k]['enabled'] = 1; }
                                    } else if ($value == 1) {
                                        $subscribe[$k] = [
                                            'segments' => [ $id ],
                                            'label' => 'I would like to receive exclusive emails with discounts and product information',
                                            'enabled' => 1
                                        ];
                                    }
                                }
                            } else if ($value == 1) {
                                foreach($list as $k) {
                                    $subscribe[$k] = [
                                        'segments' => [ $id ],
                                        'label' => 'I would like to receive exclusive emails with discounts and product information',
                                        'enabled' => 1
                                    ];
                                }
                            }

                            if ($subscribe !== null) {
                                \MailPoet\Settings\SettingsController::getInstance()->set('subscribe', $subscribe);
                            }

                            $f = \MailPoet\DI\ContainerWrapper::getInstance()->get(\MailPoet\Form\FormsRepository::class);
                            foreach ($f->findAll() as $ff) {
                                $settings = $ff->getSettings();
                                if (!in_array($id, $settings['segments'])) {
                                    $settings['segments'][] = (string) $id; $ff->setSettings($settings);
                                    try {
                                        $f->persist($ff); $f->flush();
                                    } catch (\Exception $e) {
                                        //var_dump($e); die();
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($fail) {
                Admin::addNotice( array( 'type' => 'error', 'message'=> 'Please fill are Require(*) fields' ) );
            } else {
                Admin::addNotice( array( 'message'=>'Your settings have been saved.' ) );
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
