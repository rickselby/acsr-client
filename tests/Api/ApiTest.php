<?php

namespace RickSelby\Tests\Api;

use RickSelby\Tests\TestCase;
use App\Services\ServerService;
use App\Services\ResultsService;

class ApiTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->setIP();
        \Storage::fake('ac_server');
        \Storage::fake('local');
    }

    public function testAuth()
    {
        putenv('MASTER_IP=foo');
        $this->json('GET', '/ping')
            ->seeStatusCode(401);
    }

    public function testPing()
    {
        $this->json('GET', '/ping')
            ->seeStatusCode(200)
            ->seeJson(['success' => true]);
    }

    public function testConfig()
    {
        $this->json('PUT', '/config/server', ['content' => 'foo'])
            ->seeStatusCode(200)
            ->seeJson(['updated' => true]);
        // A further call with the same content will fail
        $this->json('PUT', '/config/server', ['content' => 'foo'])
            ->seeStatusCode(200)
            ->seeJson(['updated' => false]);
    }

    public function testEntryList()
    {
        $this->json('PUT', '/config/entry-list', ['content' => 'foo'])
            ->seeStatusCode(200)
            ->seeJson(['updated' => true]);
        // A further call with the same content will fail
        $this->json('PUT', '/config/entry-list', ['content' => 'foo'])
            ->seeStatusCode(200)
            ->seeJson(['updated' => false]);
    }

    public function testStart()
    {
        $this->json('PUT', '/start')
            ->seeStatusCode(200)
            ->seeJsonStructure(['success']);
    }

    public function testStop()
    {
        $this->json('PUT', '/stop')
            ->seeStatusCode(200)
            ->seeJsonStructure(['success']);
    }

    public function testRunning()
    {
        $this->json('GET', '/running')
            ->seeStatusCode(200)
            ->seeJsonStructure(['running']);
    }

    public function testResultsLatest()
    {
        $this->json('GET', '/results/latest')
            ->seeStatusCode(200)
            ->seeJsonEquals(['results' => false]);
        // Now, if there are files?
        $content = 'foo bar';
        \Storage::disk('ac_server')->put(ResultsService::RESULTS_DIRECTORY.DIRECTORY_SEPARATOR.'1-temp', 'something');
        \Storage::disk('ac_server')->put(ResultsService::RESULTS_DIRECTORY.DIRECTORY_SEPARATOR.'2-temp', $content);
        $this->json('GET', '/results/latest')
            ->seeStatusCode(200)
            ->seeJsonEquals(['results' => $content]);
    }

    public function testResultsAll()
    {
        $this->json('GET', '/results/all')
            ->seeStatusCode(200)
            ->seeJsonEquals([]);
        // Now, if there are files?
        $fileContents = [
            '1-temp' => 'foo bar',
            '2-temp' => 'boo far',
        ];
        foreach ($fileContents as $file => $content) {
            \Storage::disk('ac_server')->put(ResultsService::RESULTS_DIRECTORY.DIRECTORY_SEPARATOR.$file, $content);
        }
        $this->json('GET', '/results/all')
            ->seeStatusCode(200)
            ->seeJsonEquals($fileContents);
    }

    /**
     * @dataProvider logFileProvider
     */
    public function testServerLog($logFile)
    {
        $this->json('GET', '/log/server')
            ->seeStatusCode(200)
            ->seeJsonEquals(['log' => '']);

        // And with an existing log file...?

        $content = uniqid();
        \Storage::disk('ac_server')->put($logFile, $content);
        $this->json('GET', '/log/server')
            ->seeStatusCode(200)
            ->seeJsonEquals(['log' => $content]);
    }

    public function testSystemLog()
    {
        $this->json('GET', '/log/system')
            ->seeStatusCode(200);
        // There's no safe way of knowing what logs might exist, given the above tests may/will generate logs...
    }

    /**********************************************************/

    public function logFileProvider()
    {
        return array_map(function ($element) {
            return [$element];
        }, ServerService::LOG_FILES);
    }

    protected function setIP()
    {
        putenv('MASTER_IP=127.0.0.1');
    }
}
