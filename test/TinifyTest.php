<?php

use Tinify\CurlMock;

class ClientTest extends TestCase {
    private $dummyFile;

    public function setUp() {
        parent::setUp();
        $this->dummyFile = __DIR__ . "/examples/dummy.png";
    }

    public function testGetKeyWithoutKeyShouldReturnNull() {
        $this->assertSame(NULL, Tinify\getKey());
    }

    public function testGetKeyWithKeyShouldReturnKey() {
        Tinify\setKey("abcde");
        $this->assertSame("abcde", Tinify\getKey());
    }

    public function testCreateKeyWithNewEmailShouldSetKey() {
        CurlMock::register("https://api.tinify.com/keys", array(
            "status" => 202,
            "body" => '{"key":"abcdefg123"}',
            "headers" => array("Content-Type" => "application/json"),
        ));

        Tinify\createKey("user@example.com", array(
            "name" => "John",
            "identifier" => "My Tinify plugin",
            "link" => "https://mywebsite.example.com/admin/settings",
        ));

        $this->assertSame("abcdefg123", Tinify\getKey());
    }

    public function testCreateKeyWithDuplicateEmailShouldThrowClientException() {
        CurlMock::register("https://api.tinify.com/keys", array(
            "status" => 403,
            "body" => '{"error":"Duplicate registration","message":"This email address has already been used"}',
        ));

        $this->setExpectedException("Tinify\AccountException");
        Tinify\createKey("user@example.com", array(
            "name" => "John",
            "identifier" => "My Tinify plugin",
            "link" => "https://mywebsite.example.com/admin/settings",
        ));
    }

    public function testKeyShouldResetClientWithNewKey() {
        CurlMock::register("https://api.tinify.com/", array("status" => 200));
        Tinify\setKey("abcde");
        Tinify\Tinify::getClient();
        Tinify\setKey("fghij");
        $client = Tinify\Tinify::getClient();
        $client->request("get", "/");

        $this->assertSame("api:fghij", CurlMock::last(CURLOPT_USERPWD));
    }

    public function testAppIdentifierShouldResetClientWithNewAppIdentifier() {
        CurlMock::register("https://api.tinify.com/", array("status" => 200));
        Tinify\setKey("abcde");
        Tinify\setAppIdentifier("MyApp/1.0");
        Tinify\Tinify::getClient();
        Tinify\setAppIdentifier("MyApp/2.0");
        $client = Tinify\Tinify::getClient();
        $client->request("get", "/");

        $this->assertSame(Tinify\Client::userAgent() . " MyApp/2.0", CurlMock::last(CURLOPT_USERAGENT));
    }

    public function testClientWithKeyShouldReturnClient() {
        Tinify\setKey("abcde");
        $this->assertInstanceOf("Tinify\Client", Tinify\Tinify::getClient());
    }

    public function testClientWithoutKeyShouldThrowException() {
        $this->setExpectedException("Tinify\AccountException");
        $this->assertInstanceOf("Tinify\Client", Tinify\Tinify::getClient());
    }

    public function testValidateWithValidKeyShouldReturnTrue() {
        Tinify\setKey("valid");
        CurlMock::register("https://api.tinify.com/shrink", array(
            "status" => 400, "body" => '{"error":"Input missing","message":"No input"}'
        ));
        $this->assertTrue(Tinify\validate());
    }

    public function testValidateWithLimitedKeyShouldReturnTrue() {
        Tinify\setKey("invalid");
        CurlMock::register("https://api.tinify.com/shrink", array(
            "status" => 429, "body" => '{"error":"Too many requests","message":"Your monthly limit has been exceeded"}'
        ));
        $this->assertTrue(Tinify\validate());
    }

    public function testValidateWithErrorShouldThrowException() {
        Tinify\setKey("invalid");
        CurlMock::register("https://api.tinify.com/shrink", array(
            "status" => 401, "body" => '{"error":"Unauthorized","message":"Credentials are invalid"}'
        ));
        $this->setExpectedException("Tinify\AccountException");
        Tinify\validate();
    }

    public function testFromFileShouldReturnSource() {
        CurlMock::register("https://api.tinify.com/shrink", array(
            "status" => 201, "headers" => array("Location" => "https://api.tinify.com/some/location")
        ));
        Tinify\setKey("valid");
        $this->assertInstanceOf("Tinify\Source", Tinify\fromFile($this->dummyFile));
    }

    public function testFromBufferShouldReturnSource() {
        CurlMock::register("https://api.tinify.com/shrink", array(
            "status" => 201, "headers" => array("Location" => "https://api.tinify.com/some/location")
        ));
        Tinify\setKey("valid");
        $this->assertInstanceOf("Tinify\Source", Tinify\fromBuffer("png file"));
    }

    public function testFromUrlShouldReturnSource() {
        CurlMock::register("https://api.tinify.com/shrink", array(
            "status" => 201, "headers" => array("Location" => "https://api.tinify.com/some/location")
        ));
        Tinify\setKey("valid");
        $this->assertInstanceOf("Tinify\Source", Tinify\fromUrl("http://example.com/test.jpg"));
    }
}
