<?php

namespace App\Controller;

use App\Exception\NotFoundException;
use App\Page;
use PHPixie\View;

/**
 * Class User
 * @package App\Controller
 * @property \App\Model\User model
 */
class User extends Page {

    public function action_login() {
        $this->view->pageTitle = "Login";
        if (!is_null($this->pixie->auth->user())) {
            $this->redirect('/account');
        }

        $this->view->returnUrl = $this->request->get('return_url', '');

        if ($this->request->method == 'POST') {

            $login = $this->model->checkLoginUser($this->request->post('username'));
            $password = $this->request->post('password');

            $user = $this->model->loadUserModel($login);

            if($user && $user->active){
                //Attempt to login the user using his
                //username and password
                $logged = $this->pixie->auth
                    ->provider('password')
                    ->login($login, $password);

                if ($logged){

                    $user->last_login = date('Y-m-d H:i:s');
                    $user->save();

                    //On successful login redirect the user to
                    //our protected page, or return_url, if specified
                    if ($this->view->returnUrl) {
                        $this->redirect($this->view->returnUrl);
                        return;
                    }

                    $this->redirect('/account');
                    return;
                }
            }
            $this->view->username = $this->request->post('username');
            $this->view->errorMessage = "Username or password are incorrect.";
        }
        //Include 'login.php' subview

        $this->view->subview = 'user/login';
    }
    
    public function action_logout() {
        if (!is_null($this->pixie->auth->user())) {
            $this->pixie->auth->logout();
        }
        $this->redirect('/');
    }

    public function action_password() {
        $this->view->pageTitle = "Restore password";
        if ($this->request->method == 'POST') {
            $email = $this->request->post('email');

            if(!empty($email)){
                $emailData = $this->model->getEmailData($email);
                if(!empty($emailData)){
                    $this->pixie->email->send($emailData['to'], $emailData['from'],$emailData['subject'],$emailData['text']);
                    $this->view->successMessage = "Check your email and restore password.";

                } else {
                    $this->view->errorMessage = "Email is incorrect.";
                }
            }
        }
        $this->view->subview = 'user/password';
    }


    public function action_register() {
        $this->view->pageTitle = "Registration";
        if (!is_null($this->pixie->auth->user())) {
            $this->redirect('/account');
        }

        $errors = [];
        
        if ($this->request->method == 'POST') {
            $dataUser = $this->getDataUser();
            $valid = true;

            if ($this->model->checkExistingUser($dataUser)) {
                $errors[] = "User already registered";
                $valid = false;
            }

            if ($valid) {
                if (!$dataUser['username']) {
                    $valid = false;
                    $errors[] = 'Please enter your username.';
                }

                if (!$dataUser['email']) {
                    $valid = false;
                    $errors[] = 'Please enter your email.';
                }

                if (!$dataUser['password'] || $dataUser['password'] != $dataUser['password_confirmation']) {
                    $valid = false;
                    $errors[] = 'Passwords are missing or not equal.';
                }

                if ($valid) {
                    $this->model->RegisterUser($dataUser);
                    $this->pixie->auth
                        ->provider('password')
                        ->login($dataUser['username'], $dataUser['password']);

                    $emailView = $this->pixie->view('user/register_email');
                    $emailView->data = $dataUser;

                    $emailData = $this->model->getEmailData($dataUser['email']);
                    $this->pixie->email->send(
                        $emailData['to'],
                        $emailData['from'],
                        'You have successfully registered on hackazon.com',
                        $emailView->render()
                    );

                    $this->redirect('/account');
                }
            }

            if (!$valid) {
                foreach ($dataUser as $key => $value) {
                    $this->view->$key = $value;
                }
            }
        }
        $this->view->errorMessage = implode('<br>', $errors);
        $this->view->subview = 'user/register';
    }

    public function action_recover(){
        if ($this->request->method == 'GET') {
            $recover_passw = $this->request->get('recover');
            if (!$recover_passw) {
                throw new NotFoundException;
            }

            $user = $this->model->getUserByRecoveryPass($recover_passw);

            if($user){
                $this->view->username = $user->username;
                $this->view->recover_passw = $recover_passw;
                $this->view->subview = 'user/recover';

            } else {
                throw new NotFoundException;
            }
        } else {
            throw new NotFoundException;
        }
    }

    public function action_newpassw(){
        if ($this->request->method == 'POST'){
            $username = $this->request->post('username');
            $recover_passw = $this->request->post('recover');
            $new_passw = $this->request->post('password');
            $confirm_passw = $this->request->post('cpassword');
            if(!empty($username) && !empty($recover_passw) && !empty($new_passw) && !empty($confirm_passw)){
                if($confirm_passw === $new_passw && $this->model->checkRecoverPass($username, $recover_passw)){
                    if($this->model->changeUserPassword($username, $new_passw)) {
                        $this->view->successMessage = "The password has been changed successfully";
                        $this->pixie->auth
                            ->provider('password')
                            ->login($username, $new_passw);
                    }
                    $this->view->subview = 'user/recover';
                    return;
                }
            }
        } else {
            throw new NotFoundException;
        }
    }

    public function action_terms()
    {
        $this->view->subview = 'user/terms';
        
    }

    private function getDataUser(){
        return array(
            'first_name' => $this->request->post('first_name'),
            'last_name' => $this->request->post('last_name'),
            'email' => $this->request->post('email'),
            'username' => $this->request->post('username'),
            'password' =>  $this->request->post('password'),
            'password_confirmation' =>  $this->request->post('password_confirmation'),
        );
    }
}