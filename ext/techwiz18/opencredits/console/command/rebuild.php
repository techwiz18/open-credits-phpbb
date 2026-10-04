<?php
/**
 * OpenCredits for phpBB — CLI: rebuild cached balances from the ledger.
 *
 * Usage: php bin/phpbbcli.php opencredits:rebuild
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\console\command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class rebuild extends \phpbb\console\command\command
{
    /** @var \techwiz18\opencredits\service\transact */
    protected $transact;

    public function __construct(\phpbb\user $user, \techwiz18\opencredits\service\transact $transact)
    {
        $this->transact = $transact;
        parent::__construct($user);
    }

    protected function configure()
    {
        $this
            ->setName('opencredits:rebuild')
            ->setDescription('Rebuild cached per-currency balances from the transaction ledger');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rows = $this->transact->rebuild_all_balances();
        $output->writeln('<info>Rebuilt ' . (int) $rows . ' balance row(s) from the ledger.</info>');
        return 0;
    }
}
