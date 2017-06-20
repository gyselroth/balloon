<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Log\Adapter;

use \Psr\Log\LogLevel;

class Stdout extends AbstractAdapter
{
    /**
     * Bash fg colors
     *
     * @var array
     */
    public $foreground_colors = [
        'black'        => '0;30',
        'dark_gray'    => '1;30',
        'blue'         => '0;34',
        'light_blue'   => '1;34',
        'green'        => '0;32',
        'light_green'  => '1;32',
        'cyan'         => '0;36',
        'light_cyan'   => '1;36',
        'red'          => '0;31',
        'light_red'    => '1;31',
        'purple'       => '0;35',
        'light_purple' => '1;35',
        'brown'        => '0;33',
        'yellow'       => '1;33',
        'light_gray'   => '0;37',
        'white'        => '1;37',
    ];


    /**
     * Bash bg colors
     *
     * @var array
     */
    public $background_colors = [
        'black'        => '40',
        'red'          => '41',
        'green'        => '42',
        'yellow'       => '43',
        'blue'         => '44',
        'magenta'      => '45',
        'cyan'         => '46',
        'light_gray'   => '47',
    ];


    /**
     * Convert to bash color
     *
     * @param   string $string
     * @param   string $forderground_color
     * @param   string $backgrond_coilor
     * @return  string
     */
    public function getColoredString(string $string, string $foreground_color = null, string $background_color = null): string
    {
        $colored_string = "";

        // Check if given foreground color found
        if (isset($this->foreground_colors[$foreground_color])) {
            $colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
        }
        // Check if given background color found
        if (isset($this->background_colors[$background_color])) {
            $colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
        }

        // Add string and end coloring
        $colored_string .=  $string . "\033[0m";

        return $colored_string;
    }


    /**
     * Log
     *
     * @param   string $level
     * @param   string $message
     * @return  bool
     */
    public function log(string $level, string $message): bool
    {
        switch ($level) {
            case LogLevel::EMERGENCY:
                $message = '['.$this->getColoredString('EMERG', 'red').']'."\t".$message;
            break;

            case LogLevel::ALERT:
                $message = '['.$this->getColoredString('ALERT', 'red').']'."\t".$message;
            break;
            
            case LogLevel::CRITICAL:
                $message = '['.$this->getColoredString('CRIT', 'red').']'."\t".$message;
            break;

            case LogLevel::ERROR:
                $message = '['.$this->getColoredString('ERR', 'light_red').']'."\t".$message;
            break;

            case LogLevel::WARNING:
                $message = '['.$this->getColoredString('WARN', 'yellow').']'."\t".$message;
            break;

            case LogLevel::INFO:
                $message = '['.$this->getColoredString('INFO', 'cyan').']'."\t".$message;
            break;

            case LogLevel::NOTICE:
                $message = '['.$this->getColoredString('NOTICE', 'green').']'."\t".$message;
            break;

            case LogLevel::DEBUG:
                $message = '['.$this->getColoredString('DEBUG', 'blue').']'."\t".$message;
            break;
        }
        
        //write to STDOUT does not work with threads
        //$result = fwrite(STDOUT, $message."\n");
        echo $message."\n";
        return true;
    }
}
