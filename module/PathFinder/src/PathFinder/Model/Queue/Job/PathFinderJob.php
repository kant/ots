<?php

namespace PathFinder\Model\Queue\Job;

use Manager\Model\Queue\Job\AbstractQueueJob;
use Manager\Entity\Job;

/**
 * Determines a conversion pathway based on initial input
 * characteristics.  Currently assumes that all input documents are
 * word processing of one sort or another, suitable for unoconv input,
 * with the exception of PDF documents.
 */
class PathFinderJob extends AbstractQueueJob
{
    /**
     * Set flags and state depending on input
     *
     * @param Job $job
     *
     * @return Job $job
     *
     * @throws Exception if input stage document can’t be found
     */
    public function process(Job $job)
    {
        // Fetch the initial input document.
        $unconvertedDocument =
            $job->getStageDocument(JOB_CONVERSION_STAGE_UNCONVERTED);
        if (!$unconvertedDocument) {
            throw new \Exception(
                "Couldn’t find the initial input document"
            );
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $unconvertedDocument->path);

        if ($mimeType == 'application/pdf') {
            $job->inputFileFormat = JOB_INPUT_TYPE_PDF;
            $job->conversionStage = JOB_CONVERSION_STAGE_PDF_IN;
            $unconvertedDocument->conversionStage =
                JOB_CONVERSION_STAGE_PDF_IN;
        } elseif (in_array($mimeType, array('text/xml','application/xml'))) {
            $job->inputFileFormat = JOB_INPUT_TYPE_XML;
            $job->conversionStage = JOB_CONVERSION_STAGE_BIBTEXREFERENCES;

            // create document.xml
            $xmlDocumentPath = $job->getDocumentPath() . "/document.xml";
            @copy($unconvertedDocument->path, $xmlDocumentPath);

            // add document.xml to job's documents
            $documentDAO = $this->sm->get('Manager\Model\DAO\DocumentDAO');
            $xmlDocument = $documentDAO->getInstance();
            $xmlDocument->path = $xmlDocumentPath;
            $xmlDocument->job = $job;
            $xmlDocument->conversionStage = JOB_CONVERSION_STAGE_XML_MERGE;

            $job->documents[] = $xmlDocument;

        } elseif (in_array($mimeType, array('application/vnd.openxmlformats-officedocument.wordprocessingml.document'))) {
            $job->inputFileFormat = JOB_INPUT_TYPE_WP;
            $job->conversionStage = JOB_CONVERSION_STAGE_DOCX;
            $unconvertedDocument->conversionStage =
                JOB_CONVERSION_STAGE_DOCX;
        } else {
            $job->inputFileFormat = JOB_INPUT_TYPE_WP;
            $job->conversionStage = JOB_CONVERSION_STAGE_WP_IN;
            $unconvertedDocument->conversionStage =
                JOB_CONVERSION_STAGE_WP_IN;
        }

        return $job;
    }
}
