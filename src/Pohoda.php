<?php
/**
 * This file is part of riesenia/pohoda package.
 *
 * Licensed under the MIT License
 * (c) RIESENIA.com
 */

declare(strict_types=1);

namespace Riesenia;

use Riesenia\Pohoda\Agenda;

/**
 * Factory for Pohoda objects.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
class Pohoda
{
    /** @var array */
    public static $namespaces = [
        'adb' => 'http://www.stormware.cz/schema/version_2/addressbook.xsd',
        'con' => 'http://www.stormware.cz/schema/version_2/contract.xsd',
        'ctg' => 'http://www.stormware.cz/schema/version_2/category.xsd',
        'dat' => 'http://www.stormware.cz/schema/version_2/data.xsd',
        'ftr' => 'http://www.stormware.cz/schema/version_2/filter.xsd',
        'inv' => 'http://www.stormware.cz/schema/version_2/invoice.xsd',
        'ipm' => 'http://www.stormware.cz/schema/version_2/intParam.xsd',
        'lst' => 'http://www.stormware.cz/schema/version_2/list.xsd',
        'ord' => 'http://www.stormware.cz/schema/version_2/order.xsd',
        'pre' => 'http://www.stormware.cz/schema/version_2/prevodka.xsd',
        'str' => 'http://www.stormware.cz/schema/version_2/storage.xsd',
        'stk' => 'http://www.stormware.cz/schema/version_2/stock.xsd',
        'typ' => 'http://www.stormware.cz/schema/version_2/type.xsd',
        'vyd' => 'http://www.stormware.cz/schema/version_2/vydejka.xsd'
    ];

    /** @var string */
    protected $_ico;

    /** @var \XMLWriter */
    protected $_xmlWriter;

    /** @var \XMLReader */
    protected $_xmlReader;

    /**
     * Constructor.
     *
     * @param string $ico
     */
    public function __construct($ico)
    {
        $this->_ico = $ico;
    }

    /**
     * Create and return instance of requested agenda.
     *
     * @param string $name
     * @param array  $data
     *
     * @return Agenda
     */
    public function create(string $name, array $data = []): Agenda
    {
        $fullName = __NAMESPACE__ . '\\Pohoda\\' . $name;

        if (!class_exists($fullName)) {
            throw new \DomainException('Not allowed entity: ' . $name);
        }

        return new $fullName($data, $this->_ico);
    }

    /**
     * Open new XML file for writing.
     *
     * @param string $filename
     * @param string $id
     * @param string $note
     *
     * @return bool
     */
    public function open(string $filename, string $id, string $note = ''): bool
    {
        $this->_xmlWriter = new \XMLWriter();

        if (!$this->_xmlWriter->openUri($filename)) {
            return false;
        }

        $this->_xmlWriter->startDocument('1.0', 'windows-1250');
        $this->_xmlWriter->startElementNs('dat', 'dataPack', null);

        $this->_xmlWriter->writeAttribute('id', $id);
        $this->_xmlWriter->writeAttribute('ico', $this->_ico);
        $this->_xmlWriter->writeAttribute('application', 'Rshop Pohoda connector');
        $this->_xmlWriter->writeAttribute('version', '2.0');
        $this->_xmlWriter->writeAttribute('note', $note);

        foreach (self::$namespaces as $k => $v) {
            $this->_xmlWriter->writeAttributeNs('xmlns', $k, null, $v);
        }

        return true;
    }

    /**
     * Add item.
     *
     * @param string $id
     * @param Agenda $agenda
     */
    public function addItem(string $id, Agenda $agenda)
    {
        $this->_xmlWriter->startElementNs('dat', 'dataPackItem', null);

        $this->_xmlWriter->writeAttribute('id', $id);
        $this->_xmlWriter->writeAttribute('version', '2.0');

        $xml = $agenda->getXML();
        if ($xml instanceof \SimpleXMLElement) {
            $this->_xmlWriter->writeRaw($xml->asXML());
        }

        $this->_xmlWriter->endElement();
        $this->_xmlWriter->flush();
    }

    /**
     * End and close XML file.
     */
    public function close()
    {
        $this->_xmlWriter->endElement();
        $this->_xmlWriter->flush();
    }

    /**
     * Load XML file.
     *
     * @param string $name
     * @param string $filename
     *
     * @return bool
     */
    public function load(string $name, string $filename)
    {
        $this->_xmlReader = new \XMLReader();

        if (!$this->_xmlReader->open($filename)) {
            return false;
        }

        $fullName = __NAMESPACE__ . '\\Pohoda\\' . $name;

        if (!class_exists($fullName)) {
            throw new \DomainException('Not allowed entity: ' . $name);
        }

        while ($this->_xmlReader->read() && $this->_xmlReader->name !== $fullName::$importRoot) {
            // skip to first element
        }

        return true;
    }

    /**
     * Get next item in loaded file.
     *
     * @return \SimpleXMLElement|null
     */
    public function next()
    {
        if (!$this->_xmlReader->name) {
            return null;
        }

        $name = $this->_xmlReader->name;

        $node = new \SimpleXMLElement($this->_xmlReader->readOuterXml());

        while ($this->_xmlReader->next() && $this->_xmlReader->name !== $name) {
            // skip to next element
        }

        return $node;
    }

    /**
     * Handle dynamic method calls.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call(string $method, array  $arguments)
    {
        // create<Agenda> method
        if (preg_match('/create([A-Z][a-zA-Z0-9]*)/', $method, $matches)) {
            return call_user_func([$this, 'create'], $matches[1], $arguments[0] ?? []);
        }

        // load<Agenda> method
        if (preg_match('/load([A-Z][a-zA-Z0-9]*)/', $method, $matches)) {
            if (!isset($arguments[0])) {
                throw new \DomainException('Filename not set.');
            }

            return call_user_func([$this, 'load'], $matches[1], $arguments[0]);
        }

        throw new \BadMethodCallException('Unknown method: ' . $method);
    }
}
