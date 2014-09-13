<?php

namespace fkooman\RemoteStorage;

use fkooman\RemoteStorage\Exception\DocumentException;
use fkooman\RemoteStorage\Exception\DocumentMissingException;

class Document
{
    private $baseDir;

    public function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    public function getBaseDir()
    {
        return $this->baseDir;
    }

    public function getDocument(Path $p)
    {
        $documentPath = $this->baseDir . $p->getPath();
        if (false === file_exists($documentPath)) {
            throw new DocumentMissingException();
        }

        $documentContent = @file_get_contents($documentPath);
        if (false === $documentContent) {
            throw new DocumentException("unable to read document");
        }

        return $documentContent;
    }

    public function putDocument(Path $p, $documentContent)
    {
        $parentFolder = $this->baseDir . $p->getParentFolder();

        // check if parent folder exists
        if (!file_exists($parentFolder)) {
            if (false === @mkdir($parentFolder, 0770, true)) {
                throw new DocumentException("unable to create directory");
            }
        }

        $documentPath = $this->baseDir . $p->getPath();
        if (false === @file_put_contents($documentPath, $documentContent)) {
            throw new DocumentException("unable to write document");
        }
    }

    /**
     * Delete a document and all empty parent directories if there are any.
     *
     * @param $p the path of a document to delete
     * @returns an array of all deleted objects
     */
    public function deleteDocument(Path $p)
    {
        if ($p->getIsFolder()) {
            throw new DocumentException("unable to delete folder");
        }

        $documentPath = $this->baseDir . $p->getPath();

        if (false === file_exists($documentPath)) {
            throw new DocumentMissingException();
        }

        if (false === @unlink($documentPath)) {
            throw new DocumentException("unable to delete file");
        }

        $deletedObjects = array();
        $deletedObjects[] = $p->getPath();

        // delete all empty folders in the tree up to the module root if
        // they are empty
        $p = new Path($p->getParentFolder());
        while (!$p->getIsModuleRoot()) {
            // not the module root
            if ($this->isEmptyFolder($p)) {
                // and it is empty, delete it
                $this->deleteFolder($p);
                $deletedObjects[] = $p->getPath();
            }
            $p = new Path($p->getParentFolder());
        }

        return $deletedObjects;
    }

    public function getFolder(Path $p)
    {
        if (!$p->getIsFolder()) {
            throw new DocumentException("not a folder");
        }

        $folderPath = $this->baseDir . $p->getPath();

        $entries = glob($folderPath . "*", GLOB_ERR|GLOB_MARK);
        if (false === $entries) {
            // directory does not exist, return empty list
            return array();
        }
        $folderEntries = array();
        foreach ($entries as $e) {
            if (is_dir($e)) {
                $folderEntries[basename($e) . "/"] = array();
            } else {
                $folderEntries[basename($e)] = array(
                    "Content-Length" => filesize($e)
                );
            }
        }

        return $folderEntries;
    }

    private function isEmptyFolder(Path $p)
    {
        if (!$p->getIsFolder()) {
            throw new DocumentException("not a folder");
        }

        $folderPath = $this->baseDir . $p->getPath();

        $entries = glob($folderPath . "*", GLOB_ERR);
        if (false === $entries) {
            throw new DocumentException("unable to read folder");
        }

        return 0 === count($entries);
    }

    private function deleteFolder(Path $p)
    {
        if (!$p->getIsFolder()) {
            throw new DocumentException("not a folder");
        }
        $folderPath = $this->baseDir . $p->getPath();
        if (false === @rmdir($folderPath)) {
            throw new DocumentException("unable to delete folder");
        }
    }
}
