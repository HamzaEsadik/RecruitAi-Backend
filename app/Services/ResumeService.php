<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;

class ResumeService
{
    /**
     * Extract text from a resume file
     */
    public function extractTextFromResume(string $resumePath, string $fileExtension): string
    {
        $extractedText = '';

        // Extract text based on file type
        if ($fileExtension === 'pdf') {
            $parser = new Parser();
            $pdf = $parser->parseFile(storage_path('app/private/' . $resumePath));
            $extractedText = $pdf->getText();
        } else if (in_array($fileExtension, ['doc', 'docx'])) {
            $phpWord = IOFactory::load(storage_path('app/private/' . $resumePath));
            $sections = $phpWord->getSections();
            foreach ($sections as $section) {
                foreach ($section->getElements() as $element) {
                    $extractedText .= $this->extractTextFromElement($element);
                }
            }
        }

        return $extractedText;
    }

    /**
     * Extract text from different types of PhpWord elements
     */
    private function extractTextFromElement($element): string
    {
        $text = '';
        
        // Check if it's a Text element
        if ($element instanceof Text) {
            return $element->getText() . ' ';
        }
        
        // Check if it's a TextRun element (contains multiple elements like texts, links, etc.)
        if ($element instanceof TextRun) {
            foreach ($element->getElements() as $subElement) {
                $text .= $this->extractTextFromElement($subElement);
            }
            return $text;
        }
        
        // Handle other element types that might contain text
        if (method_exists($element, 'getText')) {
            return $element->getText() . ' ';
        }
        
        // For elements like Table
        if (method_exists($element, 'getRows')) {
            foreach ($element->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    foreach ($cell->getElements() as $cellElement) {
                        $text .= $this->extractTextFromElement($cellElement);
                    }
                }
            }
            return $text;
        }

        return '';
    }
}