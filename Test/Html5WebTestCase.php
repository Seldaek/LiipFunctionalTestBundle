<?php

/*
 * This file is part of the Liip/FunctionalTestBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Bundle\Liip\FunctionalTestBundle\Test;

/**
 * @author Daniel Barsotti
 *
 * The on-line validator: http://validator.nu/
 * The documentation: http://about.validator.nu/
 * Documentation about the web service: http://wiki.whatwg.org/wiki/Validator.nu_Web_Service_Interface
 */
class Html5WebTestCase extends WebTestCase
{
    protected $html5Wrapper = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title></title>
</head>
<body>
<<CONTENT>>
</body>
</html>
HTML;

    protected $validationServiceAvailable = false;


    public function __construct()
    {
        parent::__construct();

        $this->testValidationServiceAvailability();
    }

    /**
     * Check if the HTML5 validation service is available
     */
    public function testValidationServiceAvailability() {

        $validationUrl = $this->getHtml5ValidatorServiceUrl();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $validationUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);

        curl_close($ch);

        $this->validationServiceAvailable = ($res !== false);
    }

    /**
     * Get the URL of the HTML5 validation service from the config
     * @return string
     */
    protected function getHtml5ValidatorServiceUrl()
    {
        return $this->getContainer()->getParameter('functionaltest.html5validationurl');
    }

    /**
     * Allows a subclass to set a custom HTML5 wrapper to test validity of HTML snippets.
     * The $wrapper may contain valid HTML5 code + the <<CONTENT>> placeholder.
     * This placeholder will be replaced by the tested snippet before validation.
     * @param string $wrapper The custom HTML5 wrapper.
     */
    protected function setHtml5Wrapper($wrapper)
    {
        $this->html5Wrapper = $wrapper;
    }

    /**
     * Run the HTML5 validation on the content and returns the results as an array
     * @param string $content The HTML content to validate
     * @return array
     */
    public function validateHtml5($content)
    {
        $validationUrl = $this->getHtml5ValidatorServiceUrl();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $validationUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'out' => 'json',
            'parser' => 'html5',
            'content' => $content,
        ));

        $res = curl_exec($ch);

        curl_close($ch);

        if ($res) {
            return json_decode($res);
        }

        // The HTML5 validation service could not be reached
        return false;
    }

    /**
     * Assert the content passed is valid HTML5. If yes, the function silently
     * runs, and if no it will make the test fail and display a summary of the
     * validation errors along with the line number where they occured.
     *
     * Warning: this function is completely agnostic about what it is actually
     * validating, thus it cannot display the validated URL. It is up to the
     * test programmer to use the $message parameter to add information about the
     * current URL. When the test fails, the message will be displayed in addition
     * to the error summary.
     *
     * @param string $content The content to validate
     * @param string $message An additional message to display when the test fails
     */
    public function assertIsValidHtml5($content, $message = '')
    {
        if (! $this->validationServiceAvailable) {
            $url = $this->getHtml5ValidatorServiceUrl();
            $this->markTestIncomplete("HTML5 Validator service not found at '$url' !");
            return;
        }

        $res = $this->validateHtml5($content);

        $err_count = 0;
        $err_msg = 'HTML5 validation failed';
        if ($message != '') {
            $err_msg .= " [$message]";
        }
        $err_msg .= ":\n";

        foreach($res->messages as $row) {
            if ($row->type == 'error') {
                // TODO: make this ugly hack to ignore fb:login-button validation errors more generic :-/
                if (preg_match('/.*(fb:login-button).*/', $row->message)) {
                    continue;
                }
                $err_count++;
                $err_msg .= "  Line {$row->lastLine}: {$row->message}\n";
            }
        }
        $this->assertTrue($err_count == 0, $err_msg);
    }

    /**
     * Assert the content passed is a valid HTML5 snippets (i.e. that the content,
     * wrapped into a basic HTML5 document body will pass the validation).
     *
     * @param string $snippet The snippet to validate
     * @param string $message An additional message to display when the test fails
     */
    public function assertIsValidHtml5Snippet($snippet, $message = '')
    {

        $content = str_replace('<<CONTENT>>', $snippet, $this->html5Wrapper);
        $this->assertIsValidHtml5($content, $message);
    }

    /**
     * Assert a JSON return from an AJAX call contains a valid HTML5 content.
     *
     * @param array $json_response The JSON Ajax reply
     * @param string $message An additional message to display when the test fails
     */
    public function assertIsValidHtml5AjaxResponse($json_response, $message = '')
    {

        $decoded_json = json_decode($json_response);

        // Check the $json_response could be decoded and that it actually contains the expected HTML.
        if ($decoded_json === null
            || ! isset($decoded_json->response)
            || count($decoded_json->response) == 0
            || ! isset($decoded_json->response[0]->html)
        ) {

            // Display an error message in the PHPUnit log in all the cases (assert true = false)
            $this->assertTrue(false, 'Invalid JSON response!');
        }

        $this->assertIsValidHtml5Snippet($decoded_json->response[0]->html, $message);
    }

    /**
     * Helper function to get a page content by creating a test client. Used to
     * avoid duplicating the same code again and again. This method also asserts
     * the request was successful.
     *
     * @param string $relativeUrl The relative URL of the requested page (i.e. the part after index.php)
     * @param string $method The HTTP method to use (GET by default)
     * @return string
     */
    public function getPage($relativeUrl, $method = 'GET') {

        $client = $this->createClient(array('environment' => 'test'));
        $client->request($method, $relativeUrl);

        $this->assertTrue($client->getResponse()->isSuccessful());

        return $client->getResponse()->getContent();
    }
}