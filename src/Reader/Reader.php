<?php

namespace avadim\FastDocxReader\Reader;

use avadim\FastDocxReader\Exceptions\Exception;

class Reader extends \XMLReader
{
    protected bool $alterMode = false;

    protected string $docxFile;

    protected ?string $innerFile = null;

    protected ?\ZipArchive $zip;

    protected array $xmlParserProperties = [];

    /** @var string[] */
    protected array $tmpFiles = [];

    /** @var string|null */
    protected static string $tempDir = '';


    /**
     * @param string $file
     * @param array|null $parserProperties
     */
    public function __construct(string $file, ?array $parserProperties = [])
    {
        $this->docxFile = $file;
        $this->zip = new \ZipArchive();
        if ($parserProperties) {
            $this->xmlParserProperties = $parserProperties;
        }
    }


    public function __destruct()
    {
        $this->close();
    }

    public function zipEntryExists(string $entryName): bool
    {
        return $this->zip->locateName($entryName) !== false;
    }

    /**
     * @param string|null $tempDir
     */
    public static function setTempDir(?string $tempDir = '')
    {
        if ($tempDir) {
            self::$tempDir = $tempDir;
            if (!is_dir($tempDir)) {
                $res = @mkdir($tempDir, 0755, true);
                if (!$res) {
                    throw new Exception('Cannot create directory "' . $tempDir . '"');
                }
            }
            self::$tempDir = realpath($tempDir);
        }
        else {
            self::$tempDir = '';
        }
    }

    /**
     * @return bool|string
     */
    protected function makeTempFile()
    {
        $name = uniqid('xlsx_reader_', true);
        if (!self::$tempDir) {
            $tempDir = sys_get_temp_dir();
            if (!is_writable($tempDir)) {
                $tempDir = getcwd();
            }
        }
        else {
            $tempDir = self::$tempDir;
        }
        $filename = $tempDir . '/' . $name . '.tmp';
        if (touch($filename, time(), time()) && is_writable($filename)) {
            $filename = realpath($filename);
            $this->tmpFiles[] = $filename;
            return $filename;
        }
        else {
            $error = 'Warning: tempdir ' . $tempDir . ' is not writeable';
            if (!self::$tempDir) {
                $error .= ', use ->setTempDir()';
            }
            throw new Exception($error);
        }
    }

    /**
     * Open an inner file of the ZIP archive
     *
     * @param string $innerFile
     * @param string|null $encoding
     * @param int|null $options
     *
     * @return bool
     */
    public function openZip(string $innerFile, ?string $encoding = null, ?int $options = null): bool
    {
        if ($options === null) {
            $options = 0;
            if (defined('LIBXML_NONET')) {
                $options = $options | LIBXML_NONET;
            }
            if (defined('LIBXML_COMPACT')) {
                $options = $options | LIBXML_COMPACT;
            }
        }
        $result = (!$this->alterMode) && $this->openXmlWrapper($innerFile, $encoding, $options);
        if (!$result) {
            $result = $this->openXmlStream($innerFile, $encoding, $options);
            $this->alterMode = $result;
        }

        return $result;
    }

    /**
     * @param string $innerFile
     * @param string|null $encoding
     * @param int|null $options
     *
     * @return bool
     */
    protected function openXmlWrapper(string $innerFile, ?string $encoding = null, ?int $options = 0): bool
    {
        $this->innerFile = $innerFile;
        $result = @$this->open('zip://' . $this->docxFile . '#' . $innerFile, $encoding, $options);
        if ($result) {
            foreach ($this->xmlParserProperties as $property => $value) {
                $this->setParserProperty($property, $value);
            }
        }

        return (bool)$result;
    }

    /**
     * Opens the INTERNAL XML file from XLSX as XMLReader
     * Example: openXml('xl/workbook.xml')
     *
     * @param string $innerPath
     * @param string|null $encoding
     * @param int|null $options
     *
     * @return bool
     */
    protected function openXmlStream(string $innerPath, ?string $encoding = null, ?int $options = 0): bool
    {
        $this->zip = new \ZipArchive();

        if ($this->zip->open($this->docxFile) !== true) {
            throw new Exception('Failed to open archive: ' . $this->docxFile);
        }

        $st = $this->zip->getStream($innerPath);
        if ($st === false) {
            throw new Exception("Internal file not found: {$innerPath}");
        }

        $tmp = $this->makeTempFile();
        $out = fopen($tmp, 'wb');
        if (!$out) {
            fclose($st);
            throw new Exception("Failed to create temporary file: {$tmp}");
        }

        stream_copy_to_stream($st, $out);
        fclose($st);
        fclose($out);

        if (!$this->open($tmp, $encoding, $options)) {
            throw new Exception("XMLReader::open() failed to open {$tmp}");
        }

        return true;
    }

    /**
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function close(): bool
    {
        $result = parent::close();
        if ($result) {
            if ($this->innerFile) {
                $this->innerFile = null;
            }
            foreach ($this->tmpFiles as $tmp) {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }

        return $result;
    }

    /**
     * @param string $tagName
     *
     * @return bool
     */
    public function seekOpenTag(string $tagName): bool
    {
        while ($this->read()) {
            if ($this->nodeType === \XMLReader::ELEMENT && $this->name === $tagName) {
                return true;
            }
        }
        return false;
    }
}