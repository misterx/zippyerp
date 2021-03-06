<?php

namespace ZippyERP\ERP\Entity\Doc;

use ZippyERP\ERP\Helper as H;
use ZippyERP\ERP\Util;

/**
 * Класс-сущность  документ счет-фактура
 *
 */
class Invoice extends Document
{

    public function generateReport() {


        $i = 1;
        $detail = array();
        $total = 0;
        foreach ($this->detaildata as $value) {
            $detail[] = array("no" => $i++,
                "tovar_name" => $value['itemname'],
                "measure" => $value['measure_name'],
                "quantity" => $value['quantity'] / 1000,
                "price" => H::fm($value['price']),
                "amount" => H::fm(($value['quantity'] / 1000) * $value['price'])
            );
            $total += ($value['quantity'] / 1000) * $value['price'];
        }

        $firm = \ZippyERP\System\System::getOptions("firmdetail");

        $f = \ZippyERP\ERP\Entity\MoneyFund::findOne('ftype = 1');
        $bank = \ZippyERP\ERP\Entity\Bank::load($f->bank);
        //  $customer = \ZippyERP\ERP\Entity\Customer::load($this->headerdata["customer"]);
        $header = array('date' => date('d.m.Y', $this->document_date),
            "firmname" => $firm['name'],
            "firmcode" => $firm['code'],
            "account" => $f->bankaccount,
            "bank" => $bank->bank_name,
            "mfo" => $bank->mfo,
            "address" => $firm['city'] . ', ' . $firm['street'],
            "customername" => $this->headerdata["customername"],
            "document_number" => $this->document_number,
            "base" => $this->base,
            "paydate" => date('d.m.Y', $this->headerdata["payment_date"]),
            "total" => H::fm($total),
            "totalnds" => H::fm($total + $this->headerdata["totalnds"]),
            "summa" => Util::ucfirst(Util::money2str($total + $this->headerdata["nds"] / 100))
        );

        $report = new \ZippyERP\ERP\Report('invoice.tpl');

        $html = $report->generate($header, $detail);

        return $html;
    }

    public function Execute() {

        return true;
    }

    public function getRelationBased() {
        $list = array();
        $list['GoodsIssue'] = 'Видаткова накладна ';
        $list['ServiceAct'] = 'Акт виконаних робіт';
        $list['TaxInvoice'] = 'Налогова   накладна ';
        return $list;
    }

    /**
     * @see Document
     */
    public function export($type) {
        if ($type == self::EX_XML_GNAU)
            return array("filename" => "invoice.xml", "content" => "<test/>");
    }

    /**
     * @see Document
     */
    public function supportedExport() {
        return array(self::EX_EXCEL, self::EX_WORD, self::EX_XML_GNAU);
    }

}
