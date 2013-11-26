<?php

namespace ZippyCMS\Store\Pages;

use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\Button;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Form\Date;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Link\SubmitLink;
use Zippy\Html\Panel;
use ZippyCMS\Core\Application as App;
use ZippyCMS\Store\Entity\Document;
use ZippyCMS\Store\Entity\Stock;
use ZippyCMS\Store\Entity\Tovar;
use ZippyCMS\Store\Helper;

/**
 * Страница  ввода  приходной  накладной
 */
class OutBill extends Base
{

        public $_tovarlist = array();
        private $_doc;

        public function __construct($docid)
        {
                parent::__construct();

                $this->add(new Form('docform'));
                $this->docform->add(new TextInput('document_number'));
                $this->docform->add(new Date('created'))->setDate(time());

                $this->docform->add(new DropDownChoice('store', \ZippyCMS\Store\Entity\Store::findArray("storename", "")))->setChangeHandler($this, 'OnChangeStore');

                $this->docform->add(new SubmitLink('addrow'))->setClickHandler($this, 'addrowOnClick');
                $this->docform->add(new SubmitButton('savedoc'))->setClickHandler($this, 'savedocOnClick');
                $this->docform->add(new Button('backtolist'))->setClickHandler($this, 'backtolistOnClick');

                $this->docform->add(new TextInput('nds'));
                $this->docform->add(new Label('total'));
                $this->add(new Form('editdetail'))->setVisible(false);
                $this->editdetail->add(new DropDownChoice('edittovar'))->setChangeHandler($this, 'OnChangeTovar');
                $this->editdetail->add(new TextInput('editquantity'))->setText("1");
                $this->editdetail->add(new TextInput('editprice'));
                $this->editdetail->add(new TextInput('editserial_number'));
                $this->editdetail->add(new Label('qtystock'));

                $this->editdetail->add(new Button('cancelrow'))->setClickHandler($this, 'cancelrowOnClick');
                $this->editdetail->add(new SubmitButton('submitrow'))->setClickHandler($this, 'saverowOnClick');

                if ($docid > 0) {    //загружаем   содержимок  документа настраницу
                        $this->_doc = Document::load($docid);
                        $this->docform->document_number->setText($this->_doc->document_number);

                        $this->docform->nds->setText($this->_doc->headerdata['nds'] / 100);
                        $this->docform->created->setDate($this->_doc->document_date);

                        $this->docform->store->setValue($this->_doc->headerdata['store']);

                        foreach ($this->_doc->detaildata as $item) {
                                $tovar = new Tovar($item);
                                $this->_tovarlist[$tovar->tovar_id] = $tovar;
                        }
                } else {
                        $this->_doc = new Document();
                        $this->_doc->type_id = 3;
                }

                $this->docform->add(new DataView('detail', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_tovarlist')), $this, 'detailOnRow'))->Reload();
        }

        public function detailOnRow($row)
        {
                $item = $row->getDataItem();

                $row->add(new Label('tovar', $item->tovarname));
                $row->add(new Label('measure', $item->measure_name));
                $row->add(new Label('serial_number', $item->serial_number));
                $row->add(new Label('quantity', $item->quantity));
                $row->add(new Label('price', number_format($item->price / 100, 2)));
                $row->add(new Label('amount', number_format($item->quantity * $item->price / 100, 2)));
                $row->add(new ClickLink('delete'))->setClickHandler($this, 'deleteOnClick');
        }

        public function deleteOnClick($sender)
        {
                $tovar = $sender->owner->getDataItem();
                // unset($this->_tovarlist[$tovar->tovar_id]);

                $this->_tovarlist = array_diff_key($this->_tovarlist, array($tovar->tovar_id => $this->_tovarlist[$tovar->tovar_id]));
                $this->docform->detail->Reload();
        }

        public function addrowOnClick($sender)
        {
                $this->editdetail->setVisible(true);
                $this->docform->setVisible(false);
                $this->editdetail->edittovar->setOptionList(Stock::findArrayEx("quantity > 0 and store_id=" . $this->docform->store->getValue()));
        }

        public function saverowOnClick($sender)
        {
                $id = $this->editdetail->edittovar->getValue();
                if ($id == 0) {
                        $this->setError("Не выбран товар");
                        return;
                }
                $stock = Stock::load($id);
                $stock->quantity = $this->editdetail->editquantity->getText();
                $stock->partion = $stock->price;
                $stock->price = $this->editdetail->editprice->getText() * 100;
                $stock->serial_number = $this->editdetail->editserial_number->getText();

                $this->_tovarlist[$stock->tovar_id] = $stock;
                $this->editdetail->setVisible(false);
                $this->docform->setVisible(true);
                $this->docform->detail->Reload();

                //очищаем  форму
                $this->editdetail->edittovar->setValue(0);
                $this->editdetail->editquantity->setText("1");
                $this->editdetail->editserial_number->setText("");
                $this->editdetail->editprice->setText("");
        }

        public function cancelrowOnClick($sender)
        {
                $this->editdetail->setVisible(false);
                $this->docform->setVisible(true);
        }

        public function OnChangeTovar($sender)
        {
                $store_id = $sender->getValue();
                $stock = Stock::load($store_id);
                //  $this->editdetail->editserial_number->setValue($stock->serial_number);
                $this->editdetail->qtystock->setText($stock->quantity . ' ' . $stock->measure_name);
        }

        public function savedocOnClick($sender)
        {
                if ($this->checkForm() == false) {
                        return;
                }

                $this->calcTotal();

                $this->_doc->headerdata = array(
                    'store' => $this->docform->store->getValue(),
                    'nds' => $this->docform->nds->getValue() * 100
                );
                $this->_doc->detaildata = array();
                foreach ($this->_tovarlist as $tovar) {
                        $this->_doc->detaildata[] = $tovar->getData();
                }

                $this->_doc->amount = 100 * $this->docform->total->getText();
                $this->_doc->document_number = $this->docform->document_number->getText();
                $this->_doc->document_date = strtotime($this->docform->created->getText());
                $this->_doc->save();
                App::Redirect('\ZippyCMS\Store\Pages\DocList');
        }

        /**
         * Расчет  итого
         * 
         */
        private function calcTotal()
        {
                $total = 0;
                foreach ($this->_tovarlist as $tovar) {
                        $total = $total + $tovar->price / 100 * $tovar->quantity;
                }
                $this->docform->total->setText(number_format($total + $this->docform->nds->getText(), 2));
        }

        /**
         * Валидация   формы
         * 
         */
        private function checkForm()
        {

                if (count($this->_tovarlist) == 0) {
                        $this->setError("Не введен ни один  товар");
                        return false;
                }
                return true;
        }

        public function beforeRender()
        {
                parent::beforeRender();

                $this->calcTotal();
        }

        public function backtolistOnClick($sender)
        {
                App::Redirect("\\ZippyCMS\\Store\\Pages\\DocList");
        }

        public function OnChangeStore($sender)
        {
                //очистка  списка  товаров
                $this->_tovarlist = array();
                $this->docform->detail->Reload();
        }

}