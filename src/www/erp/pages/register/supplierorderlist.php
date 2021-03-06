<?php

namespace ZippyERP\ERP\Pages\Register;

use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use ZippyERP\ERP\Entity\Customer;
use ZippyERP\ERP\Entity\Doc\Document;
use ZippyERP\ERP\Entity\Doc\SupplierOrder;
use ZippyERP\System\Filter;
use ZippyERP\ERP\Helper as H;
use Zippy\WebApplication as App;

/**
 * журнал  докуметов - заказов  поставщику
 */
class SupplierOrderList extends \ZippyERP\ERP\Pages\Base
{

    /**
     *
     * @param mixed $docid Документ  должен  быть  показан  в  просмотре
     * @return DocList
     */
    public function __construct($docid = 0) {
        parent::__construct();
        $filter = Filter::getFilter("SupplierOrderList");
        $this->add(new Form('filter'))->onSubmit($this, 'filterOnSubmit');
        $this->filter->add(new DropDownChoice('statelist', SupplierOrder::getStatesList()));
        $this->filter->add(new \Zippy\Html\Form\AutocompleteTextInput('supplierlist'))->onText($this, 'OnAutoCustomer');



        if (strlen($filter->state) > 0)
            $this->filter->statelist->setValue($filter->state);
        if (strlen($filter->supplier) > 0) {
            $this->filter->supplierlist->setKey($filter->supplier);
            $this->filter->supplierlist->setText($filter->suppliername);
        }


        $doclist = $this->add(new DataView('doclist', new DocSODataSource(), $this, 'doclistOnRow'));
        $doclist->setSelectedClass('table-success');
        $doclist->Reload();
        $this->add(new \ZippyERP\ERP\Blocks\DocView('docview'))->setVisible(false);
        if ($docid > 0) {
            $this->docview->setVisible(true);
            $this->docview->setDoc(Document::load($docid));
            //$this->doclist->setSelectedRow($docid);
            $doclist->Reload();
        }
        $this->add(new \Zippy\Html\DataList\Paginator('pag', $doclist));
    }

    public function doclistOnRow($row) {
        $item = $row->getDataItem();
        $supplier = Customer::load($item->datatag);
        $item = $item->cast();
        $row->add(new Label('number', $item->document_number));
        $row->add(new Label('date', date('d-m-Y', $item->document_date)));
        $row->add(new Label('supplier', ($supplier) ? $supplier->customer_name : ""));
        $row->add(new Label('amount', ($item->amount > 0) ? H::fm($item->amount) : ""));

        $row->add(new Label('state', Document::getStateName($item->state)));
        $row->add(new ClickLink('show'))->onClick($this, 'showOnClick');
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
        //закрытый период
        if ($item->updated < strtotime("2013-01-01")) {
            $row->edit->setVisible(false);
            $row->cancel->setVisible(false);
        }
    }

    public function filterOnSubmit($sender) {
        $this->docview->setVisible(false);
        //запоминаем  форму   фильтра
        $filter = Filter::getFilter("SupplierOrderList");
        $filter->state = $this->filter->statelist->getValue();
        $filter->supplier = $this->filter->supplierlist->getKey();
        $filter->suppliername = $this->filter->supplierlist->getText();

        $this->doclist->Reload();
    }

    public function editOnClick($sender) {
        $item = $sender->owner->getDataItem();
        $type = H::getMetaType($item->type_id);
        $class = "\\ZippyERP\\ERP\\Pages\\Doc\\" . $type['meta_name'];
        //   $item = $class::load($item->document_id);
        App::Redirect($class, $item->document_id);
    }

    public function showOnClick($sender) {
        $item = $sender->owner->getDataItem();
        $this->docview->setVisible(true);
        $this->docview->setDoc($item);
        $this->doclist->setSelectedRow($sender->getOwner());
        $this->doclist->Reload();
    }

    public function OnAutoCustomer($sender) {
        $text = Customer::qstr('%' . $sender->getText() . '%');
        return Customer::findArray("customer_name", "Customer_name like " . $text);
    }

}

/**
 *  Источник  данных  для   списка  документов
 */
class DocSODataSource implements \Zippy\Interfaces\DataSource
{

    private function getWhere() {

        //$conn = \ZDB\DB::getConnect();
        $filter = Filter::getFilter("SupplierOrderList");
        $where = " meta_name ='SupplierOrder' ";

        if ($filter->state > 0) {
            $where .= " and state =  " . $filter->state;
        }
        if ($filter->supplier > 0) {
            $where .= " and datatag =  " . $filter->supplier;
        }

        return $where;
    }

    public function getItemCount() {
        return Document::findCnt($this->getWhere());
    }

    public function getItems($start, $count, $sortfield = null, $asc = null) {
        return Document::find($this->getWhere(), "document_date " . $asc, $count, $start);
    }

    public function getItem($id) {
        
    }

}
