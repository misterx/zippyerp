<?php

namespace ZippyERP\ERP\Entity\Doc;

use ZippyERP\ERP\Entity\Entry;
use ZippyERP\ERP\Entity\Stock;
use ZippyERP\ERP\Entity\SubConto;
use ZippyERP\ERP\Helper as H;

/**
 * Класс-сущность  документ для ручных  операций
 * и  ввода начальных остатков
 *
 */
class ManualEntry extends Document
{

    protected function init() {
        parent::init();
    }

    public function Execute() {
        $accarr = unserialize(base64_decode($this->headerdata['entry']));
        foreach ($accarr as $entry) {


            Entry::AddEntry($entry->acc_d, $entry->acc_c, $entry->amount, $this->document_id, $this->document_date);
        }

        //ТМЦ
        $itemarr = unserialize(base64_decode($this->headerdata['item']));
        if (is_array($itemarr)) {

            foreach ($itemarr as $item) {
                $stock = Stock::getStock($item->store_id, $item->item_id, $item->price, true);
                $acc = explode('_', $item->op);
                $sc = new SubConto($this, $acc[0], $acc[1] == 'd' ? ($item->qty) / 1000 * $stock->price : 0 - ($item->qty) / 1000 * $stock->price);
                $sc->setStock($stock->stock_id);
                $sc->setQuantity($acc[1] == 'd' ? $item->qty : 0 - $item->qty);
                $sc->save();
            }
        }
        //сотрудники (лицевые  счета)
        $emparr = unserialize(base64_decode($this->headerdata['emp']));
        if (count($emparr) > 0) {

            foreach ($emparr as $emp) {

                $val = $emp->val;
                $acc = explode('_', $emp->op);

                $sc = new SubConto($this, $acc[0], $acc[1] == 'd' ? $val : 0 - $val);
                $sc->setEmployee($emp->employee_id);
                $sc->save();
            }
        }
        //контрагенты (взаиморасчеты)
        $carr = unserialize(base64_decode($this->headerdata['c']));
        if (count($carr) > 0) {

            foreach ($carr as $c) {
                $val = $c->val;
                $acc = explode('_', $c->op);

                $sc = new SubConto($this, $acc[0], $acc[1] == 'd' ? $val : 0 - $val);
                $sc->setCustomer($c->customer_id);
                $sc->save();
            }
        }
        //денежные  счета
        $farr = unserialize(base64_decode($this->headerdata['f']));
        if (count($farr) > 0) {

            foreach ($farr as $f) {
                $val = $f->val;
                $acc = explode('_', $f->op);

                $sc = new SubConto($this, $acc[0], $acc[1] == 'd' ? $val : 0 - $val);
                $sc->setMoneyfund($f->id);
                $sc->save();
            }
        }

        //ОС
        $caarr = unserialize(base64_decode($this->headerdata['ca']));
        if (is_array($caarr)) {

            foreach ($caarr as $item) {
                $acc = explode('_', $item->op);
                $sc = new SubConto($this, $acc[0], $acc[1] == 'd' ? ($item->qty) / 1000 * $item->price : 0 - ($item->qty) / 1000 * $item->price);
                $sc->setAsset($item->item_id);
                $sc->setQuantity($acc[1] == 'd' ? $item->qty : 0 - $item->qty);
                $sc->save();
            }
        }
    }

    public function generateReport() {

        $header = array(
            'date' => date('d.m.Y', $this->document_date),
            "description" => $this->headerdata["description"],
            "document_number" => $this->document_number
        );
        //$detail = array();
        $i = 1;
        $arr = array();
        $accarr = unserialize(base64_decode($this->headerdata['entry']));
        foreach ($accarr as $entry) {
            $arr[] = array("no" => $i++,
                "acc_d" => $entry->acc_d,
                "acc_c" => $entry->acc_c,
                "amount" => H::fm($entry->amount));
        }
        $header['entry?'] = count($arr);
        $header['entry'] = $arr;

        //ТМЦ
        $arr = array();
        $itemarr = unserialize(base64_decode($this->headerdata['item']));
        foreach ($itemarr as $item) {
            $op = str_replace('_d', ' Дебет', $item->op);
            $op = str_replace('_c', ' Кредит', $op);
            $arr[] = array("no" => $i++,
                "opname" => $op,
                "store_name" => $item->store_name,
                "itemname" => $item->itemname,
                "qty" => $item->qty / 1000,
                "price" => H::fm($item->price),
                "amount" => H::fm($item->price * ($item->qty / 1000)));
        }
        $header['item?'] = count($arr);
        $header['item'] = $arr;

        //Сотрудники
        $arr = array();
        $itemarr = unserialize(base64_decode($this->headerdata['emp']));
        foreach ($itemarr as $item) {

            $op = str_replace('_d', ' Дебет', $item->op);
            $op = str_replace('_c', ' Кредит', $op);
            $arr[] = array("no" => $i++,
                "opname" => $op,
                "name" => $item->fullname,
                "amount" => H::fm($item->val));
        }
        $header['emp?'] = count($arr);
        $header['emp'] = $arr;

        //Контрагенты
        $arr = array();
        $itemarr = unserialize(base64_decode($this->headerdata['c']));
        foreach ($itemarr as $item) {
            $op = str_replace('_d', ' Дебет', $item->op);
            $op = str_replace('_c', ' Кредит', $op);
            $arr[] = array("no" => $i++,
                "opname" => $op,
                "optype" => $item->type == 1 ? "Покупець" : "Постачальник",
                "name" => $item->customer_name,
                "amount" => H::fm($item->val));
        }
        $header['c?'] = count($arr);
        $header['c'] = $arr;

        //Денежные  счета
        $arr = array();
        $itemarr = unserialize(base64_decode($this->headerdata['f']));
        foreach ($itemarr as $item) {
            $op = str_replace('_d', ' Дебет', $item->op);
            $op = str_replace('_c', ' Кредит', $op);

            $arr[] = array("no" => $i++,
                "opname" => $op,
                "name" => $item->title,
                "amount" => H::fm($item->val));
        }
        $header['f?'] = count($arr);
        $header['f'] = $arr;

        //Основные средства
        $arr = array();
        $itemarr = unserialize(base64_decode($this->headerdata['ca']));
        foreach ($itemarr as $item) {
            $op = str_replace('_d', ' Дебет', $item->op);
            $op = str_replace('_c', ' Кредит', $op);

            $arr[] = array("no" => $i++,
                "opname" => $op,
                "name" => $item->itemname,
                "cnt" => $item->qty / 1000,
                "price" => H::fm($item->price));
        }
        $header['ca?'] = count($arr);
        $header['ca'] = $arr;


        $report = new \ZippyERP\ERP\Report('manualentry.tpl');

        $html = $report->generate($header);

        return $html;
    }

    //для програмного создания

    public $_entryarr = array();
    public $_itemarr = array();
    public $_emparr = array();
    public $_carr = array();
    public $_caarr = array();
    public $_farr = array();
    public $_acclist = array();  //список  счетов  из  проводок
    public $_accalllist = array();  //список  счетов  из  проводок

    public function upload($date, $number, $desc = "") {
        $this->document_date = $date;
        $this->document_number = $number;
        $this->headerdata["description"] = $desc;

        $this->headerdata['entry'] = base64_encode(serialize($this->_entryarr));
        $this->headerdata['emp'] = base64_encode(serialize($this->_emparr));
        $this->headerdata['item'] = base64_encode(serialize($this->_itemarr));
        $this->headerdata['c'] = base64_encode(serialize($this->_carr));
        $this->headerdata['ca'] = base64_encode(serialize($this->_caarr));
        $this->headerdata['f'] = base64_encode(serialize($this->_farr));

        $this->save();
        $this->updateStatus(Document::STATE_EXECUTED);
    }

    /**
     * Добавить  проводку
     * 
     * @param mixed $ct
     * @param mixed $dt
     * @param mixed $amount
     */
    public function AddEntry($dt, $ct, $amount) {
        $entry = new \ZippyERP\ERP\Entity\Entry();
        $entry->entry_id = time();
        $entry->acc_c = $ct;
        $entry->acc_d = $dt;
        $entry->amount = 100 * $amount;
        if ($entry->amount == 0) {
            return;
        }
        $this->_entryarr[$entry->entry_id] = $entry;
    }

    public function AddItem($store_id, $item_id, $op, $price, $qty, $itemname, $storename = "") {
        $item = new \ZippyERP\ERP\Entity\Item();
        $item->op = $op;
        $item->store_id = $store_id;
        $item->price = 100 * $price;
        $item->qty = 1000 * $qty;
        $item->store_name = $storename;
        $item->itemname = $itemname;

        $this->_itemarr[$item_id] = $item;
    }

}
