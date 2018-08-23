<?php
/**
 * @file
 * Contains SelectFile
 */

namespace zviryatko\GDocHist\Command;

use GuzzleHttp\Psr7\Response;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use function Functional\map as map;
use function Functional\reindex as reindex;
use Symfony\Component\Console\Question\Question;

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
            //'pageSize' => 10,
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
            $this->options() + ['' => 'Create new file'],
            ''
        );
        $question->setErrorMessage('File %s is invalid.');

        $fileId = $helper->ask($input, $output, $question);
        try {
            if (empty($fileId)) {
                $fileId = $this->create($input, $output);
            }
            $this->export($fileId);
        } catch (\Google_Service_Exception $e) {
            $data = json_decode($e->getMessage());
            throw new RuntimeException($data->error->message, $data->error->code, $e);
        }
    }

    private function export($fileId)
    {
        $file = $this->service->files->get($fileId);
        $export = $this->service->files->export($fileId, 'application/pdf', [
            'alt' => 'media',
            'mimeType' => 'text/html',
//            'mimeType' => 'application/pdf',
        ]);
        if ($export instanceof Response) {
            $path = 'data/' . $file->getName() . '.html';
//            $path = 'data/' . $file->getName() . '.pdf';
            $this->prepareFile($path);
            $content = (string) $export->getBody();
            $content = $this->purify($content);
//            $content = $this->convertToMd($content);
            $this->saveFile($path, $content);
        }
    }

    private function create(InputInterface $input, OutputInterface $output): string
    {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $name = $helper->ask($input, $output, new Question('Provide file name: '));

        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.document'));
        $file = $this->service->files->create($fileMetadata, array(
            'fields' => 'id'));
        return $file->id;
    }

    private function prepareFile(string $path): void
    {
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path));
        }
        if (!file_exists($path)) {
            touch($path);
        }
    }

    private function convertToMd(string $content): string
    {
        $converter = new HtmlConverter(array('strip_tags' => true));
        return $converter->convert($content);
    }

    private function saveFile(string $path, string $content): void
    {
        $out = fopen($path, 'rw+');
        fwrite($out, $content);
        fclose($out);
    }

    private function purify(string $content): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($content);
    }
}
