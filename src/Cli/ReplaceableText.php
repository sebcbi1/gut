<?php
/**
 * Project: gut
 * User: sebcbi1
 * Date: 14/05/16
 * Time: 11:44
 */

namespace Gut\Cli;

use League\CLImate\TerminalObject\Dynamic\DynamicTerminalObject;

class ReplaceableText extends DynamicTerminalObject
{

    private $oldText = '';

    public function set($text = '')
    {
        $output = '';
        $this->output->sameLine();
        if (!empty($this->oldText)) {
            $output .= $this->util->cursor->left(strlen($this->oldText));
            $output .= str_pad('', strlen($this->oldText));
            $output .= $this->util->cursor->left(strlen($this->oldText));
        }
        $this->output->write($this->parser->apply($output . $text));
        $this->oldText = $text;
    }

}
