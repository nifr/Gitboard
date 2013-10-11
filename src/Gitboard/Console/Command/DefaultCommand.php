<?php

namespace Gitboard\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\TableHelper;

// TODO include current state ( dirty / clean - files modified / deleted / created ) - git status --porcelain
// TODO include option to show ignored files 
// TODO include option to show deleted files
// TODO create command to eval in bashrc
class DefaultCommand extends Command
{
    protected $target;

    protected $branch;

    protected $commits = array();

    protected $stats = array();

    protected $input;

    protected $output;

    // TODO investigate getHelper()
    protected $formatter;

    // TODO investigate getHelper()
    protected $table;

    protected $options = array();

private static $logo = "  ____ _ _   _                         _  
  / ___(_) |_| |__   ___   __ _ _ __ __| | 
 | |  _| | __| '_ \ / _ \ / _` | '__/ _` | 
 | |_| | | |_| |_) | (_) | (_| | | | (_| | 
  \____|_|\__|_.__/ \___/ \__,_|_|  \__,_| 
                                          ";

    public function getHelp()
    {
        return self::$logo . parent::getHelp();
    }

    protected function configure()
    {
        $this
            ->setName('gitboard')
            ->setDescription('Simple dashboard for a quick overview of Git projects.')
            ->addOption('relative', null, InputOption::VALUE_OPTIONAL, 'Output relative commit dates?', 'true')
            ->addOption('clear', null, InputOption::VALUE_OPTIONAL, 'Clear screen before output? (not available in cmd.exe)', 'true')
        ;
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
            case 'clear':
                //
                break;
        }
    }

    // TODO execution flow? initialize -> execute -> interact ?
    protected function intitialize(InputInterface $input, OutputInterface $output)
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln(
                $this->getHelperSet()->get('formatter')->formatSection('DEBUG','Invoking ' . __METHOD__ . '()' . PHP_EOL, 'info')
            );
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln(
                $this->getHelperSet()->get('formatter')->formatSection('DEBUG','Invoking ' . __METHOD__ . '()' . PHP_EOL, 'info')
            );
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln(
                $this->getHelperSet()->get('formatter')->formatSection('DEBUG','Invoking ' . __METHOD__ . '()' . PHP_EOL, 'info')
            );
        }

        // make input/output accessible by local functions
        $this->input = $input;
        $this->output = $output;

        //$this->target = $_SERVER['PWD'];
        $this->target = getcwd();
        $this->importOptions();

        // TODO getHelper() - https://github.com/symfony/symfony/blob/master/src/Symfony/Component/Console/Command/Command.php
        $helperSet = $this->getHelperSet();
        $this->formatter = $helperSet->get('formatter');
        $this->table = $helperSet->get('table');
        // TOOD try/catch with exception
        $this->branch = $this->getCurrentBranch();
        // TODO make configurable
        $this->getCommits(40, $this->commits);
        $this->getStats($this->commits, $this->stats);
        // RENDERING
        $this->clearScreen();
        $this->renderLogo();
        $this->renderHeader();
        $this->renderCommits();
        $this->renderStats($this->stats, $this->table);
    }

    protected function renderLogo()
    {
        $this->output->writeln(
            $this->formatter->formatBlock($this::$logo, 'fg=blue')
        );
        $this->output->writeln('');
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
        $this->output->writeln('');

        $this->clearTable($this->table);
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
        $this->output->writeln('');

        $this->clearTable($this->table);
    }

    protected function clearTable($table)
    {
        $table->setHeaders(array());
        $table->setRows(array());
    }

    // TODO typehint table
    // TODO get table helper directly from output?
    protected function renderStats(array $stats, $table)
    {
        // TODO check if no commits
        $table->setHeaders(array(
            'name',
            'commits',
            '%',
            'files',
            '%',
        ));

        array_walk($stats, (function($stat) use (&$table) {
            $table->addRow(array(
                $stat['name'],
                $stat['totalCommits'],
                $stat['percentCommits'],
                $stat['totalFiles'],
                $stat['percentFiles'],
            ));
        }));

        $table->render($this->output);
        $this->output->writeln('');

        $this->clearTable($table);
    }

    // TODO move to provider
    protected function getCurrentBranch()
    {
        // TODO use better branch function
        $cmd = sprintf("git --git-dir=%s/.git branch --no-color 2>&1", $this->target);

        // TODO move to function / use AOP for verbosity somehow?
        // http://symfony.com/doc/current/components/console/events.html#the-consoleevents-command-event
        if (OutputInterface::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
            $this->output->write(
                $this->formatter->formatSection('DEBUG','Executing command: ' . PHP_EOL, 'info'),
                false
            );
            $this->output->writeln(
                $this->formatter->formatSection('DEBUG', $cmd . PHP_EOL, 'info')
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
        // TODO any way to clear the screen from PHP CLI in cmd.exe/powershell?
        if ( isset($_ENV["ComSpec"]) ) {
            return;
        }

        // don't clear screen if verbose output 
        if (OutputInterface::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
            $this->output->writeln(
                $this->formatter->formatSection('DEBUG','Skipping ' . __METHOD__ . '()' . PHP_EOL, 'info')
            );

            return;
        }

        if ($this->options['clear']) {
            passthru("tput clear");
        }
    }

    // TODO move to provider
    protected function getCommits($nbDays)
    {
        $separator = '°';
        // TODO make date-format configurable
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

        // TODO move to process execution method / use git provider
        if (OutputInterface::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
            $this->output->write(
                $this->formatter->formatSection('DEBUG','Executing command: ' . PHP_EOL, 'info'),
                false
            );
            $this->output->writeln(
                $this->formatter->formatSection('DEBUG', $cmd . PHP_EOL, 'info')
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
        // same as this? https://github.com/KuiKui/Gitboard/blob/master/gitboard.php#L380
        array_walk_recursive(
            $commits,
            (function(&$value) {
                $value = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $value);
            })
        );

        $this->commits = $commits;
    }

    // TODO move to provider
    protected function getCommitFromLine($line, $separator)
    {
        $elements = explode($separator, $line);

        $commit = array(
            'date'          => $elements[0],
            'date_relative' => $elements[1],
            'email'         => $elements[2],
            'name'          => $elements[3],
            'hash'          => $elements[4],
            'message'       => $elements[5],
            'files'         => array()
        );

        return $commit;
    }

    // TODO refactor - source https://github.com/KuiKui/Gitboard/blob/master/gitboard.php#L279
    protected function getStats(array $commits, &$stats = array())
    {
        $stats = array();
        $nbCommits = 0;
        $nbFiles = 0;

        // TODO what if git user email is empty?
        foreach($commits as $commit)
        {
            if(!isset($stats[$commit['email']]))
            {
                $stats[$commit['email']] = array(
                    'name'           => $commit['name'],
                    'totalCommits'   => 0,
                    'percentCommits' => 0,
                    'totalFiles'     => 0,
                    'percentFiles'   => 0
                );
            }

            $stats[$commit['email']]['name'] = $commit['name'];
            $stats[$commit['email']]['totalCommits'] += 1;
            $stats[$commit['email']]['totalFiles'] += count($commit['files']);
            $nbCommits++;
            $nbFiles += count($commit['files']);
        }

        foreach($stats as $key => $stat)
        {
            $stats[$key]['percentCommits'] = round($stat['totalCommits'] * 100 / $nbCommits);
            $stats[$key]['percentFiles']   = round($stat['totalFiles'] * 100 / $nbFiles);
        }

        return $stats;
    }
}
