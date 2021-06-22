<?php

namespace FireflyIII\Console\Commands;

use Illuminate\Console\Command;
use FireflyIII\Models\Account;
use FireflyIII\Models\Transaction;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Models\Category;
use FireflyIII\Models\TransactionType;
use Baraveli\BMLTransaction\BML;
use Carbon\Carbon;
use FireflyIII\User;
use Illuminate\Support\Facades\DB;

class BMLSyncCommand extends Command
{
    protected $bml;

    private const TRANSACTION_TYPE_WITHDRAW = "Withdrawal";
    private const TRANSACTION_TYPE_DEPOSIT = "Deposit";
    private const TRANSACTION_TYPE_TRANSFER = "Transfer";

    private const BML_TRANSFER_CREDIT = "Transfer Credit";
    private const BML_TRANSFER_DEBIT = "Transfer Debit";

    protected $mappings = [
        "Transfer Credit" => "Deposit",
        "Transfer Debit" => "Withdrawal",
        "Purchase" => "Withdrawal"
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bml:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync bml transactions into database.';

    /**
     * Create a new command instance.  
     *        
     * @return void  
     */
    public function __construct(BML $bml)
    {
        parent::__construct();

        $this->bml = $bml;
    }

    /**
     * Execute the console cxommand.
     *
     * @return int
     */
    public function handle()
    {
        $todays_transactions = $this->bml->login(
            config('services.bml.username'),
            config('services.bml.password'),
            config('services.bml.account_id')
        )->GetTodayTransactions();

        if (count($todays_transactions["history"]) > 0) {
            DB::transaction(function () use ($todays_transactions) {

                $currency = TransactionCurrency::firstOrCreate(["code" => "MVR"], [
                    "name" => "Maldivian Rufiyaa",
                    "symbol" => "MVR",
                    "decimal_places" => 2
                ]);

                collect($todays_transactions["history"])->each(function ($transaction, $key) use ($currency) {

                    $account = Account::first();
                    $account->transactions()->create([
                        "transaction_currency_id" => $currency->id,
                        "amount" => (string) $transaction["amount"],
                        "description" =>  $transaction["description"],
                        "transaction_journal_id" => TransactionJournal::create([
                            "user_id" => User::first()->id,
                            "transaction_type_id" => TransactionType::where('type', $this->mappings[$transaction["description"]])->first()->id,
                            "transaction_currency_id" => $currency->id,
                            "description" => "BML - " . $transaction["description"],
                            "date" => Carbon::parse($transaction["bookingDate"]),
                            "completed" => 1,
                            "tag_count" => 0
                        ])->id
                    ]);
                });
            });
        }
    }
}