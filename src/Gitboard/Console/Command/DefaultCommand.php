<?php

namespace Gitboard\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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

    protected function configure()
    {
        $this
            ->setName('gitboard')
            ->setDescription('Say hello')
        ;

        //$this->target = $_SERVER['PWD'];
        $this->target = getcwd();

        // 
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // make input/output accessible by local functions
        $this->input = $input;
        $this->output = $output;

        // setup helpersets
        $helperSet = $this->getHelperSet();
        $this->formatter = $helperSet->get('formatter');
        $this->table = $helperSet->get('table');

        // TODO only on platforms where available
        $this->clearScreen();

        // TOOD try/catch with exception
        $this->branch = $this->getCurrentBranch();

        // TODO move to function
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
        $this->table->render($output);

        $this->table->setRows(array());

        $this->output->writeln('');
        $this->output->writeln('');

        // TODO make configurable
        $this->commits = $this->getCommits(40);

        // TODO check if there are commits -> render message if not

        $this->table->setHeaders(array(
            'date','name','hash','message','files'
        ));

        for($i = 0; $i < count($this->commits); $i++)
        {
            if(!isset($this->commits[$i])) continue;
            /*
            displayValue($converter, date('d/m/y H\hi', strtotime($commits[$i]['date'])), 17, "0;33", false, date('d/m/y'));
            displayValue($converter, limitText($commits[$i]['name'], 16), 17);
            displayValue($converter, $commits[$i]['hash'], 8);
            displayValue($converter, limitText($commits[$i]['message'], 70), 71, "0;36");
            displayValue($converter, count($commits[$i]['files']), 9);
            outputf($converter, "\n");
            */
            $date = date('d/m/y H\hi', strtotime($this->commits[$i]['date']));
            // @nico Important - strip non-ansii characters
            $name = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $this->commits[$i]['name']);
            $hash = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $this->commits[$i]['hash']);
            $message = substr($this->commits[$i]['message'],0,6);
            $files = count($this->commits[$i]['files']);

            $this->table->addRow(array(
                $date,$name,$hash,$message,$files,
            ));

        }

        $this->table->render($output);

    }

    // TODO move to provider
    protected function getCurrentBranch()
    {
        $cmd = sprintf("git --git-dir=%s/.git branch --no-color 2>&1", $this->target);

        $this->output->writeln('executing: ' . $cmd);
        $this->output->writeln('');

        // TODO use symfony/process
        // read lines into branch array
        $outputArray = explode("\n", exec($cmd));

        // filter lines beginning with "* " aka the branches
        $outputArray = preg_grep("/\* /", $outputArray);

        // strip "* " from the beginning of branch
        array_walk($outputArray, function(&$value, $key){
            $value = substr($value, 2);
        });

        if(count($outputArray) === 0)
        {
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
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // TODO warn on verbose that clearing screen is not supported
            // TODO check for cygwin/msys
        } 
        //passthru("tput clear");

        // ncurses_clear() ?
    }

    // TODO move to provider
    protected function getCommits($nbDays)
    {
        $separator = '°';
        $from = date('Y-m-d 00:00:00', strtotime(sprintf("-%s days", $nbDays - 1)));
        // TODO replace with git command
        $cmd = sprintf('git --git-dir=%s/.git log --no-merges --ignore-all-space --since="%s" --format="%%ci%s%%ce%s%%cn%s%%h%s%%s" --numstat', $this->target, $from, $separator, $separator, $separator, $separator);
        exec($cmd, $results);

        $commits = array();
        $commit = array();
        foreach($results as $line)
        {
            if(strlen($line) == 0)
            {
                continue;
            }
            if(strpos($line, $separator) !== false)
            {
                if(count($commit) > 0)
                {
                    $commits[] = $commit;
                }
                $commit = $this->getCommitFromLine($line, $separator);
            }
            else
            {
                $elements = preg_split("/[\s]+/", $line, null, PREG_SPLIT_NO_EMPTY);
                $commit['files'][] = array(
                    'add' => $elements[0],
                    'delete' => $elements[1],
                    'file' => $elements[2],
                );
            }
        }

        if(count($commit) > 0)
        {
            $commits[] = $commit;
        }

        return $commits;
    }

    protected function getCommitFromLine($line, $separator)
    {
        $elements = explode($separator, $line);
        $commit = array(
            'date' => date('Y-m-d H:i:s', strtotime($elements[0])),
            'email' => $elements[1],
            'name' => $elements[2],
            'hash' => $elements[3],
            'message' => $elements[4],
            'files' => array()
        );

        return $commit;
    }
}