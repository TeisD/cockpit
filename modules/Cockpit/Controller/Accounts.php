<?php

namespace Cockpit\Controller;

class Accounts extends \Cockpit\AuthController {

    public function index() {

        if (!$this->module('cockpit')->hasaccess('cockpit', 'accounts')) {
            return $this->helper('admin')->denyRequest();
        }

        $current  = $this->user['_id'];
        $groups   = $this->module('cockpit')->getGroups();

        return $this->render('cockpit:views/accounts/index.php', compact('current', 'groups'));
    }


    public function account($uid=null) {

        if (!$uid) {
            $uid = $this->user['_id'];
        }

        if (!$this->module('cockpit')->hasaccess('cockpit', 'accounts') && $uid != $this->user['_id']) {
            return $this->helper('admin')->denyRequest();
        }

        $account = $this->app->storage->findOne('cockpit/accounts', ['_id' => $uid]);

        if (!$account) {
            return false;
        }

        unset($account["password"]);

        $fields    = $this->app->retrieve('config/account/fields', null);
        $languages = $this->getLanguages();
        $groups    = $this->module('cockpit')->getGroups();

        return $this->render('cockpit:views/accounts/account.php', compact('account', 'uid', 'languages', 'groups', 'fields'));
    }

    public function create() {

        if (!$this->module('cockpit')->hasaccess('cockpit', 'accounts')) {
            return $this->helper('admin')->denyRequest();
        }

        $uid       = null;
        $account   = ['user'=>'', 'email'=>'', 'active'=>true, 'group'=>'admin', 'i18n'=>$this->app->helper('i18n')->locale];

        $fields    = $this->app->retrieve('config/account/fields', null);
        $languages = $this->getLanguages();
        $groups    = $this->module('cockpit')->getGroups();

        return $this->render('cockpit:views/accounts/account.php', compact('account', 'uid', 'languages', 'groups', 'fields'));
    }

    public function save() {

        if ($data = $this->param('account', false)) {

            // check rights
            if (!$this->module('cockpit')->hasaccess('cockpit', 'accounts')) {

                if (!isset($data['_id']) || $data['_id'] != $this->user['_id']) {
                    return $this->helper('admin')->denyRequest();
                }
            }

            $data['_modified'] = time();

            if (!isset($data['_id'])) {
                $data['_created'] = $data['_modified'];
            }

            if (isset($data['password'])) {

                if (strlen($data['password'])){
                    $data['password'] = $this->app->hash($data['password']);
                } else {
                    unset($data['password']);
                }
            }

            $this->app->storage->save('cockpit/accounts', $data);

            if (isset($data['password'])) {
                unset($data['password']);
            }

            if ($data['_id'] == $this->user['_id']) {
                $this->module('cockpit')->setUser($data);
            }

            return json_encode($data);
        }

        return false;

    }

    public function remove() {

        if ($data = $this->param('account', false)) {

            // user can't delete himself
            if ($data['_id'] != $this->user['_id']) {

                $this->app->storage->remove('cockpit/accounts', ['_id' => $data['_id']]);

                return '{"success":true}';
            }
        }

        return false;
    }

    public function find() {

        $options = array_merge([
            'sort'   => ['user' => 1]
        ], $this->param('options', []));

        if (isset($options['filter'])) {

            if (is_string($options['filter'])) {

                $options['filter'] = [
                    '$or' => [
                        ['name' => ['$regex' => $options['filter']]],
                        ['user' => ['$regex' => $options['filter']]],
                        ['email' => ['$regex' => $options['filter']]],
                    ]
                ];
            }
        }

        $accounts = $this->storage->find('cockpit/accounts', $options)->toArray();
        $count    = (!isset($options['skip']) && !isset($options['limit'])) ? count($accounts) : $this->storage->count('cockpit/accounts', isset($options['filter']) ? $options['filter'] : []);
        $pages    = isset($options['limit']) ? ceil($count / $options['limit']) : 1;
        $page     = 1;

        if ($pages > 1 && isset($options['skip'])) {
            $page = ceil($options['skip'] / $options['limit']) + 1;
        }

        foreach ($accounts as &$account) {

            $account['md5email'] = md5(@$account['email']);

            if (isset($account["password"])) {
                unset($account["password"]);
            }
        }

        return compact('accounts', 'count', 'pages', 'page');
    }

    protected function getLanguages() {

        $languages = [['i18n' => 'en', 'language' => 'English']];

        foreach ($this->app->helper('fs')->ls('*.php', '#config:cockpit/i18n') as $file) {

            $lang     = include($file->getRealPath());
            $i18n     = $file->getBasename('.php');
            $language = $lang['@meta']['language'] ?? $i18n;

            $languages[] = ['i18n' => $i18n, 'language'=> $language];
        }

        return $languages;
    }

}
