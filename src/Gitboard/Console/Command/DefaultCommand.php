<?php

namespace Gitboard\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\TableHelper;

class DefaultCommand extends Command
{
    protected $target;

    protected $branch;

    protected $commits;

    protected $input;

    protected $output;

    protected $formatter;

    protected $table;

    protected $options = array();

    protected function configure()
    {
        $this
            ->setName('gitboard')
            ->setDescription('Simple dashboard for a quick overview of Git projects.')
            ->addOption('relative', null, InputOption::VALUE_OPTIONAL, 'Output relative commit dates?', 'true')
            ->addOption('clear', null, InputOption::VALUE_OPTIONAL, 'Clear screen before output? (not available in cmd.exe)', 'true')
        ;

        //$this->target = $_SERVER['PWD'];
        $this->target = getcwd();

        //
    }

    protected function importOptions()
    {

        // TERMINAL DIMENSIONS
        $terminalDimensions = $this->getApplication()->getTerminalDimensions();
        $this->options['terminal'] = array(
         'width'  => $terminalDimensions[0],
         'height' => $terminalDimensions[1],
        );

        // RELATIVE DATES
        $this->options['relative'] = filter_var($this->input->getOption('relative'), FILTER_VALIDATE_BOOLEAN);

        // CLEAR SCREEN
        $this->options['clear'] = filter_var($this->input->getOption('clear'), FILTER_VALIDATE_BOOLEAN);
    }

    // TODO return validation callback ?
    protected function validate($option)
    {
        switch ($option) {
            case 'relative':
                //if (!in_array($this->input->getOption('relative'), array('true', 'false'), true)) {
                //    throw new \Exception('The required option can only be "true", "false" or empty.');
                //}
                break;
            case 1:
                echo "i equals 1";
                break;
            case 2:
                echo "i equals 2";
                break;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // make input/output accessible by local functions
        $this->input = $input;
        $this->output = $output;

        $this->importOptions();

        // setup helpersets
        $helperSet = $this->getHelperSet();
        $this->formatter = $helperSet->get('formatter');
        $this->table = $helperSet->get('table');

        // TODO only on platforms where available
        $this->clearScreen();

        // TOOD try/catch with exception
        $this->branch = $this->getCurrentBranch();

        $this->renderHeader();

        $this->output->writeln('');
        $this->output->writeln('');

        // TODO make configurable
        $this->getCommits(40, $this->commits);

        $this->renderCommits();
    }

    protected function renderHeader()
    {
        // TODO move to table render function
        $this->table
            /** defaults to TableHelper::LAYOUT_DEFAULT , LAYOUT_COMPACT */
            /** @see https://github.com/symfony/symfony/blob/master/src/Symfony/Component/Console/Helper/TableHelper.php#L24 */
            // TODO make static call, include use statement
            ->setLayout(TableHelper::LAYOUT_COMPACT)
            ->addRow(array(
                // todo simple format
                $this->formatter->formatBlock('project', 'info'),
                $this->target,
            ))
            ->addRow(array(
                $this->formatter->formatBlock('current branch', 'info'),
                $this->branch,
            ))
            ->addRow(array(
                $this->formatter->formatBlock('current date', 'info'),
                date('d/m/Y H:i:s'),
            ))
        ;
        $this->table->render($this->output);

        // TODO create clearTable function
        $this->table->setRows(array());
    }

    // TODO introduce render-limit
    protected function renderCommits()
    {
        // TODO check if there are commits -> render message if not

        $this->table->setHeaders(array(
            'date','name','hash','message','files'
        ));

        // TODO introduce render-limit
        for ($i = 0; $i < count($this->commits); $i++) {
            $this->table->addRow(array(
                ($this->options['relative']) ? $this->commits[$i]['date_relative'] : date('d/m/y H\hi', $this->commits[$i]['date']),
                $this->commits[$i]['name'],
                $this->commits[$i]['hash'],
                // TODO cut function for table with
                substr($this->commits[$i]['message'],0,20),
                count($this->commits[$i]['files']),
            ));

        }
        $this->table->render($this->output);
    }

    // TODO move to provider
    protected function getCurrentBranch()
    {
        // TODO use better branch function
        $cmd = sprintf("git --git-dir=%s/.git branch --no-color 2>&1", $this->target);

        // TODO move to function / use AOP for verbosity somehow?
        // http://symfony.com/doc/current/components/console/events.html#the-consoleevents-command-event
        if (OutputInterface::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
            $this->output->writeln(
                $this->formatter->formatSection('DEBUG','executing: ' . $cmd . PHP_EOL, 'info')
            );
        }

        // TODO use symfony/process
        // read lines into branch array
        $outputArray = explode("\n", exec($cmd));

        // filter lines beginning with "* " aka the branches
        $outputArray = preg_grep("/\* /", $outputArray);

        // strip "* " from the beginning of branch
        array_walk($outputArray, function(&$value, $key){
            $value = substr($value, 2);
        });

        if (count($outputArray) === 0) {
            // TODO configure exit quietly / verbose with different messages
            // - not a git repository
            // - no branch checked out?
            $this->output->writeln(
                $this->formatter->formatSection('Info','No branch selected in ' . $this->target . PHP_EOL, 'info')
            );
            exit();
        }

        return $outputArray[0];
    }

    protected function clearScreen()
    {
        //passthru("tput clear");
        //if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // TODO warn on verbose that clearing screen is not supported
        //}
        if ($this->options['clear']) {
        // TODO check for cygwin/msys
            passthru("tput clear");
        }

        // TODO check wether this works on windows?
        // ncurses_clear() ?
    }

    // TODO move to provider
    protected function getCommits($nbDays)
    {
        $separator = '°';
        $from = date('Y-m-d 00:00:00', strtotime(sprintf("-%s days", $nbDays - 1)));

        // TODO configurable git command
        // TODO move to symfony/process
        // --abbrev-comit = short unique commits
        // --no-color = no ansi colors
        // --numstat = similar to stat but easily compute readable
        // git --format options https://www.kernel.org/pub/software/scm/git/docs/git-show.html
        // %h  - abbreviated commit hash
        // %ce - email
        // %ci - committer date, ISO 8601 format
        // %cr - committer date, relative
        $cmd = sprintf('git --git-dir=%s/.git log --no-color --no-merges --abbrev-commit --ignore-all-space --since="%s" --format="%%ct%s%%cr%s%%ce%s%%cn%s%%h%s%%s" --numstat 2>&1', $this->target, $from, $separator, $separator, $separator, $separator, $separator);
        //--format="%ci_%ce_%cn_%h__"

        if (OutputInterface::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
            $this->output->writeln(
                $this->formatter->formatSection('DEBUG','executing: ' . $cmd . PHP_EOL, 'info')
            );
        }

        exec($cmd, $results);

        // remove empty lines
        $results = array_filter($results);

        $i=0;
        $commits = array();

        array_walk($results, function($line) use ($separator,&$commits,&$i) {
            if (strpos($line, $separator) !== false) {
                $commits[] = $this->getCommitFromLine($line, $separator);
                $i++;
            } else {
                if ($i > 0) {
                    $elements = preg_split("/[\s]+/", $line, null, PREG_SPLIT_NO_EMPTY);
                    $commits[($i-1)]['files'][] = array(
                        'add'    => $elements[0],
                        'delete' => $elements[1],
                        'file'   => $elements[2],
                    );
                }
            }
        });

        // important - strip non ascii characters from output ( added by @nifr )
        array_walk_recursive(
            $commits,
            (function(&$value) {
                $value = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $value);
            })
        );

        $this->commits = $commits;
    }

    protected function getCommitFromLine($line, $separator)
    {
        $elements = explode($separator, $line);
        $commit = array(
            'date' => $elements[0],
            'date_relative' => $elements[1],
            'email' => $elements[2],
            'name' => $elements[3],
            'hash' => $elements[4],
            'message' => $elements[5],
            'files' => array()
        );

        return $commit;
    }
}
