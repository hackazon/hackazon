<?php

namespace App\Model;

class Faq extends BaseModel {
    public $table = 'tbl_faq';
    public $id_field = 'faqID';

    public function getEntries() {
        return $this->pixie->db->query('select')
            ->table('tbl_faq')
            ->execute();
    }
    
    public function create($post)
    {
        $this->email = $post['userEmail'];
        $this->question = $post['userQuestion'];
        $this->answer = 'Processing...';
//        if (!is_null($this->pixie->auth->user())) {
//            $this->customer_id = $this->pixie->auth->user()->id;
//        }
        $this->save();
        return $this;
    }
}