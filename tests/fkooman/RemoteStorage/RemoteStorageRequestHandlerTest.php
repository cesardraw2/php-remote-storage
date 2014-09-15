<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\RemoteStorage;

use PDO;
use fkooman\OAuth\ResourceServer\TokenIntrospection;
use fkooman\Http\Request;

use PHPUnit_Framework_TestCase;

class RemoteStorageRequestHandlerTest extends PHPUnit_Framework_TestCase
{
    private $r;

    public function setUp()
    {
        $md = new MetadataStorage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            )
        );
        $md->initDatabase();

        $tempFile = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        mkdir($tempFile);
        $document = new DocumentStorage($tempFile);
        $remoteStorage = new RemoteStorage($md, $document);

        $introspect = new TokenIntrospection(
            array(
                "active" => true,
                "sub" => "admin"
            )
        );

        $this->r = new RemoteStorageRequestHandler($remoteStorage, $introspect);
    }

    public function testPutDocument()
    {
        $request = new Request("https://www.example.org", "PUT");
        $request->setPathInfo("/admin/foo/bar/baz.txt");
        $request->setContentType("text/plain");
        $request->setContent("Hello World!");
        $response = $this->r->handleRequest($request);
        $this->assertEquals("application/json", $response->getContentType());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetDocument()
    {
        $request = new Request("https://www.example.org", "PUT");
        $request->setPathInfo("/admin/foo/bar/baz.txt");
        $request->setContentType("text/plain");
        $request->setContent("Hello World!");
        $response = $this->r->handleRequest($request);

        $request = new Request("https://www.example.org");
        $request->setPathInfo("/admin/foo/bar/baz.txt");
        $response = $this->r->handleRequest($request);
        $this->assertEquals("text/plain", $response->getContentType());
        $this->assertEquals("Hello World!", $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetNonExistingDocument()
    {
        $request = new Request("https://www.example.org");
        $request->setPathInfo("/admin/foo/bar/baz.txt");
        $response = $this->r->handleRequest($request);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteDocument()
    {
        $request = new Request("https://www.example.org", "PUT");
        $request->setPathInfo("/admin/foo/bar/baz.txt");
        $request->setContentType("text/plain");
        $request->setContent("Hello World!");
        $response = $this->r->handleRequest($request);

        $request = new Request("https://www.example.org", "DELETE");
        $request->setPathInfo("/admin/foo/bar/baz.txt");
        $response = $this->r->handleRequest($request);
        $this->assertEquals(200, $response->getStatusCode());

    }

    public function testDeleteNonExistingDocument()
    {
        $request = new Request("https://www.example.org", "DELETE");
        $request->setPathInfo("/admin/foo/bar/baz.txt");
        $response = $this->r->handleRequest($request);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetNonExistingFolder()
    {
        $request = new Request("https://www.example.org", "GET");
        $request->setPathInfo("/admin/foo/bar/");
        $response = $this->r->handleRequest($request);
        $this->assertEquals("application/ld+json", $response->getContentType());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            array(
                "@context" => "http://remotestorage.io/spec/folder-description",
                "items" => array()
            ),
            $response->getContent()
        );
    }

    public function testGetFolder()
    {
        $request = new Request("https://www.example.org", "PUT");
        $request->setPathInfo("/admin/foo/bar/baz.txt");
        $request->setContentType("text/plain");
        $request->setContent("Hello World!");
        $this->r->handleRequest($request);

        $request = new Request("https://www.example.org", "GET");
        $request->setPathInfo("/admin/foo/bar/");
        $response = $this->r->handleRequest($request);
        $this->assertEquals("application/ld+json", $response->getContentType());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            array(
                "@context" => "http://remotestorage.io/spec/folder-description",
                "items" => array(
                    "baz.txt" => array(
                        "Content-Type" => "text/plain",
                        "Content-Length" => 12,
                        "ETag" => "1"
                    )
                )
            ),
            $response->getContent()
        );

    }
}
