<?php
/**
 * @file
 * Contains SelectFile
 */

namespace zviryatko\GDocHist\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use function Functional\map as map;
use function Functional\reindex as reindex;

final class SelectFile extends Command
{

    /**
     * @var \Google_Service_Drive
     */
    private $service;

    public function __construct(string $name, \Google_Service_Drive $service)
    {
        parent::__construct($name);
        $this->service = $service;
    }

    private function options()
    {
        $optParams = [
            'pageSize' => 10,
            'fields' => 'nextPageToken, files(id, name)',
        ];
        $results = $this->service->files->listFiles($optParams);

        $getId = function (\Google_Service_Drive_DriveFile $file) {
            return $file->getId();
        };
        $getName = function (\Google_Service_Drive_DriveFile $file) {
            return $file->getName();
        };
        return map(reindex($results->getFiles(), $getId), $getName);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Please select the file',
            $this->options()
        );
        $question->setErrorMessage('File %s is invalid.');

        $fileId = $helper->ask($input, $output, $question);
        try {
            $this->export($fileId);
        } catch (\Google_Service_Exception $e) {
            $data = json_decode($e->getMessage());
            throw new RuntimeException($data->error->message, $data->error->code, $e);
        }
    }

    private function export($fileId)
    {
        $file = $this->service->files->get($fileId);
        $export = $this->service->files->export($fileId, 'application/pdf', ['alt' => 'media']);
    }
}
