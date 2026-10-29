<?php

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Services\Domain\Account\AccountHardDeletionService;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Throwable;

class DeleteSeptember10SignupWaveCommand extends Command
{
    private const SIGNUP_DAY_START = '2026-09-10 00:00:00';

    private const SIGNUP_DAY_END = '2026-09-11 00:00:00';

    private const OWNER_TIMEZONE_PREFIX = 'Asia/';

    private const ACCOUNT_IDS = [
        9874, 9875, 9876, 9877, 9878, 9879, 9880, 9881, 9882, 9883, 9884, 9885, 9886, 9887, 9888,
        9889, 9890, 9891, 9892, 9893, 9894, 9895, 9896, 9897, 9898, 9899, 9900, 9901, 9902, 9903,
        9904, 9905, 9906, 9907, 9908, 9909, 9910, 9911, 9912, 9913, 9914, 9915, 9916, 9917, 9918,
        9919, 9920, 9921, 9922, 9923, 9924, 9925, 9926, 9929, 9930, 9931, 9932, 9933, 9934, 9935,
        9936, 9937, 9938, 9939, 9940, 9941, 9942, 9943, 9944, 9945, 9946, 9947, 9948, 9949, 9950,
        9951, 9952, 9953, 9954, 9956, 9957, 9958, 9959, 9960, 9961, 9962, 9963, 9964, 9965, 9966,
        9967, 9968, 9969, 9970, 9971, 9972, 9973, 9974, 9975, 9976, 9977, 9978, 9979, 9980, 9981,
        9982, 9983, 9984, 9985, 9986, 9987, 9988, 9989, 9990, 9991, 9992, 9994, 9995, 9996, 9997,
        9998, 9999, 10000, 10001, 10002, 10003, 10004, 10005, 10006, 10007, 10008, 10009, 10010, 10011, 10012,
        10013, 10014, 10016, 10017, 10018, 10019, 10020, 10021, 10023, 10024, 10025, 10026, 10027, 10028, 10029,
        10030, 10031, 10032, 10033, 10034, 10035, 10036, 10037, 10038, 10039, 10040, 10041, 10042, 10043, 10044,
        10045, 10046, 10047, 10048, 10049, 10050, 10051, 10052, 10053, 10054, 10055, 10056, 10057, 10058, 10059,
        10060, 10062, 10063, 10064, 10065, 10066, 10067, 10068, 10069, 10070, 10071, 10072, 10073, 10074, 10076,
        10077, 10078, 10079, 10080, 10081, 10082, 10083, 10084, 10085, 10086, 10087, 10088, 10090, 10091, 10092,
        10093, 10094, 10095, 10097, 10098, 10099, 10100, 10101, 10102, 10103, 10104, 10105, 10106, 10107, 10108,
        10109, 10110, 10111, 10112, 10113, 10114, 10115, 10116, 10117, 10118, 10119, 10120, 10121, 10122, 10123,
        10125, 10126, 10127, 10128, 10129, 10130, 10131, 10132, 10133, 10134, 10135, 10136, 10137, 10138, 10139,
        10140, 10141, 10142, 10143, 10144, 10145, 10146, 10147, 10148, 10149, 10150, 10151, 10152, 10153, 10154,
        10155, 10156, 10157, 10158, 10159, 10160, 10161, 10162, 10163, 10164, 10166, 10167, 10168, 10169, 10170,
        10171, 10172, 10173, 10174, 10175, 10176, 10177, 10178, 10180, 10181, 10182, 10183, 10185, 10187, 10188,
        10189, 10190, 10191, 10192, 10193, 10194, 10195, 10196, 10197, 10198, 10199, 10200, 10202, 10204, 10205,
        10206, 10207, 10208, 10209, 10210, 10211, 10213, 10214, 10215, 10216, 10217, 10218, 10220, 10221, 10222,
        10223, 10224, 10225, 10226, 10227, 10228, 10229, 10230, 10231, 10232, 10233, 10234, 10235, 10236, 10237,
        10238, 10240, 10241, 10242, 10243, 10244, 10245, 10246, 10247, 10248, 10249, 10250, 10251, 10252, 10253,
        10254, 10255, 10256, 10257, 10258, 10259, 10260, 10261, 10262, 10263, 10264, 10265, 10266, 10267, 10268,
        10269, 10270, 10271, 10272, 10273, 10274, 10275, 10276, 10277, 10278, 10279, 10280, 10281, 10282, 10283,
        10286, 10287, 10288, 10289, 10290, 10291, 10292, 10293, 10294, 10295, 10296, 10297, 10298, 10299, 10300,
        10301, 10302, 10303, 10304, 10305, 10306, 10307, 10308, 10309, 10310, 10311, 10312, 10313, 10314, 10315,
        10316, 10317, 10318, 10319, 10320, 10321, 10322, 10324, 10325, 10326, 10327, 10328, 10329, 10330, 10331,
        10332, 10333, 10334, 10335, 10336, 10337, 10339, 10340, 10341, 10342, 10343, 10344, 10345, 10346, 10347,
        10348, 10349, 10350, 10351, 10352, 10353, 10354, 10355, 10356, 10357, 10358, 10359, 10360, 10361, 10362,
        10363, 10365, 10366, 10367, 10368, 10370, 10371, 10372, 10374, 10376, 10377, 10378, 10379, 10380, 10381,
        10382, 10383, 10384, 10385, 10386, 10387, 10388, 10389, 10390, 10391, 10392, 10394, 10395, 10398, 10399,
        10400, 10401, 10402, 10403, 10405, 10407, 10409, 10410, 10411, 10412, 10413, 10414, 10415, 10416, 10417,
        10418, 10420, 10421, 10422, 10423, 10424, 10425, 10426, 10427, 10428, 10429, 10430, 10431, 10432, 10434,
        10435, 10436, 10437, 10438, 10439, 10440, 10441, 10443, 10444, 10445, 10446, 10447, 10449, 10450, 10451,
        10452, 10453, 10454, 10455, 10456, 10457, 10458, 10460, 10461, 10462, 10463, 10464, 10465, 10466, 10467,
        10468, 10469, 10470, 10471, 10472, 10473, 10474, 10475, 10476, 10477, 10478, 10479, 10480, 10481, 10482,
        10483, 10484, 10485, 10486, 10487, 10488, 10489, 10491, 10492, 10493, 10494, 10496, 10497, 10498, 10500,
        10502, 10503, 10505, 10506, 10507, 10509, 10510, 10511, 10512, 10514, 10516, 10517, 10518, 10519, 10521,
        10522, 10523, 10526, 10529, 10530, 10531, 10532, 10533, 10535, 10536, 10537, 10538, 10539, 10540, 10541,
        10542, 10543, 10544, 10545, 10546, 10549, 10551, 10552, 10554, 10555, 10556, 10557, 10559, 10562, 10564,
        10565, 10566, 10567, 10568, 10571, 10572, 10573, 10574, 10575, 10577, 10579, 10581, 10583, 10584, 10586,
        10590, 10591, 10592, 10593, 10594, 10595, 10596, 10597, 10598, 10599, 10600, 10602, 10603, 10604, 10605,
        10606, 10607, 10608, 10609, 10610, 10611, 10612, 10613, 10614, 10615, 10616, 10617, 10619, 10620, 10621,
        10623, 10626, 10627, 10632, 10634, 10635, 10637, 10638, 10639, 10641, 10642, 10645, 10646, 10647, 10649,
        10650, 10651, 10652, 10653, 10655, 10656, 10657, 10661, 10662, 10664, 10666, 10667, 10668, 10669, 10671,
        10672, 10673, 10675, 10676, 10677, 10678, 10679, 10681, 10683, 10684, 10691, 10692, 10694, 10695, 10697,
        10699, 10700, 10701, 10702, 10705, 10707, 10708, 10710, 10711, 10712, 10713, 10716, 10718, 10719, 10720,
        10721, 10722, 10723, 10724, 10726, 10727, 10728, 10731, 10732, 10733, 10734, 10735, 10736, 10737, 10739,
        10740, 10741, 10742, 10743, 10744, 10745, 10746, 10748, 10750, 10751, 10752, 10754, 10756, 10757, 10759,
        10760, 10761, 10763, 10766, 10767, 10768, 10769, 10770, 10772, 10773, 10774, 10781, 10782, 10783, 10788,
        10789, 10791, 10792, 10793, 10794, 10795, 10796, 10797, 10798, 10799, 10800, 10802, 10803, 10804, 10806,
        10808, 10809, 10810, 10811, 10812, 10813, 10814, 10816, 10820, 10821, 10822, 10824, 10826, 10831, 10832,
        10842, 10843, 10844, 10845, 10848, 10849, 10850, 10852, 10853, 10854, 10859, 10861, 10863, 10865, 10869,
        10870, 10871, 10875, 10877, 10881, 10882, 10883, 10885, 10889, 10890, 10891, 10893, 10894, 10895, 10896,
        10897, 10899, 10901, 10903, 10907, 10910, 10911, 10912, 10916, 10918, 10919, 10920, 10921, 10922, 10925,
        10927, 10929, 10934, 10935, 10936, 10940, 10941, 10943, 10944, 10945, 10947, 10948, 10951, 10954, 10955,
        10956, 10959, 10961, 10963, 10964, 10966, 10967, 10968, 10972, 10974, 10978, 10980, 10983, 10984, 10985,
        10987, 10989, 10990, 10992, 10994, 10997, 10999, 11000, 11008, 11009, 11010, 11011, 11012, 11013, 11016,
        11019, 11020, 11022, 11023, 11026, 11028, 11029, 11030, 11031, 11032, 11035, 11036, 11037, 11042, 11045,
        11047, 11048, 11050, 11051, 11052, 11054, 11059, 11061, 11062, 11063, 11064, 11068, 11069, 11072, 11075,
        11081, 11082, 11084, 11089, 11091, 11097, 11099, 11100, 11103, 11107, 11108, 11109, 11110, 11112, 11114,
        11115, 11118, 11119, 11120, 11121, 11122, 11124, 11125, 11128, 11132, 11138, 11139, 11140, 11145, 11146,
        11147, 11150, 11153, 11154, 11155, 11157, 11158, 11159, 11162, 11170, 11173, 11174, 11177, 11179, 11180,
        11181, 11182, 11183, 11187, 11189, 11191, 11194, 11195, 11197, 11198, 11201, 11202, 11210, 11213, 11215,
        11218, 11221, 11222, 11226, 11228, 11232, 11234, 11237, 11240, 11241, 11246, 11248, 11250, 11253, 11254,
        11255, 11258, 11263, 11264, 11265, 11266, 11268, 11269, 11270, 11271, 11272, 11274, 11276, 11277, 11278,
        11279, 11282, 11286, 11289, 11295, 11300, 11301, 11302, 11303, 11304, 11305, 11307, 11308, 11310, 11311,
        11313, 11316, 11317, 11318, 11321, 11325, 11326, 11328, 11331, 11332, 11334, 11338, 11340, 11342, 11345,
        11346, 11347, 11348, 11350, 11351, 11353, 11356, 11360, 11361, 11364, 11367, 11368, 11369, 11370, 11374,
        11375, 11376, 11381, 11382, 11386, 11393, 11395, 11397, 11398, 11400, 11403, 11405, 11411, 11412, 11413,
        11414, 11416, 11417, 11420, 11421, 11422, 11423, 11424, 11427, 11428, 11430, 11434, 11435, 11436, 11437,
        11440, 11441, 11442, 11446, 11449, 11450, 11452, 11453, 11454, 11455, 11459, 11460, 11463, 11468, 11469,
        11470, 11471, 11479, 11483, 11495, 11496, 11497, 11501, 11503, 11505, 11508, 11510, 11511, 11512, 11513,
        11516, 11518, 11519, 11520, 11521, 11528, 11535, 11538, 11539, 11540, 11541, 11542, 11543, 11544, 11546,
        11547, 11549, 11550, 11552, 11553, 11554, 11555, 11558, 11561, 11563, 11565, 11571, 11574, 11576, 11578,
        11580, 11581, 11582, 11583, 11584, 11586, 11588, 11589, 11592, 11593, 11594, 11595, 11596, 11597, 11598,
        11599, 11600, 11602, 11603, 11606, 11608, 11611, 11619, 11620, 11623, 11626, 11629, 11631, 11632, 11635,
        11636, 11637, 11638, 11639, 11640, 11641, 11644, 11646, 11647, 11649, 11652, 11657, 11658, 11661, 11662,
        11663, 11664, 11665, 11666, 11667, 11669, 11670, 11671, 11673, 11679, 11680, 11681, 11686, 11689, 11690,
        11692, 11694, 11695, 11696, 11697, 11698, 11700, 11702, 11703, 11705, 11708, 11710, 11711, 11712, 11715,
        11716, 11724, 11725, 11727, 11728, 11729, 11731, 11732, 11737, 11742, 11744, 11745, 11754, 11755, 11756,
        11758, 11760, 11761, 11764, 11766, 11772, 11776, 11777, 11778, 11785, 11789, 11791, 11794, 11795, 11796,
        11797, 11798, 11799, 11805, 11806, 11807, 11809, 11811, 11817, 11820, 11823, 11827, 11829, 11830, 11831,
        11833, 11834, 11835, 11843, 11848, 11849, 11851, 11855, 11860, 11872, 11873, 11874, 11875, 11878, 11879,
        11881, 11882, 11883, 11884, 11885, 11886, 11887, 11888, 11891, 11892, 11893, 11901, 11902, 11903, 11904,
        11905, 11910, 11917, 11918, 11919, 11920, 11922, 11923, 11924, 11925, 11926, 11930, 11931, 11934, 11936,
        11938, 11940, 11958, 11961, 11963, 11964,
    ];

    protected $signature = 'accounts:delete-2026-09-10-signups
                            {--limit= : Delete at most this many accounts in this run}
                            {--force : Permanently delete the accounts instead of only listing them}';

    protected $description = 'Permanently delete the unused accounts, and their owners, from the 2026-09-10 signup wave';

    public function handle(DatabaseManager $databaseManager, AccountHardDeletionService $hardDeletionService): int
    {
        $limit = $this->option('limit');

        if ($limit !== null && (int) $limit < 1) {
            $this->error('--limit must be a positive number.');

            return self::FAILURE;
        }

        $candidates = $this->findCandidates($databaseManager->connection());

        [$deletable, $skipped] = $candidates->partition(fn (object $candidate): bool => $candidate->skip_reasons === []);

        $this->info(sprintf(
            '%d of the %d listed accounts can be deleted, %d skipped, %d already deleted or no longer owned by a 2026-09-10 signup.',
            $deletable->count(),
            count(self::ACCOUNT_IDS),
            $skipped->count(),
            count(self::ACCOUNT_IDS) - $candidates->count(),
        ));

        if ($skipped->isNotEmpty()) {
            $this->table(
                ['Account', 'Owner', 'Skipped because'],
                $skipped->map(fn (object $candidate): array => [
                    $candidate->account_id,
                    $candidate->email,
                    implode(', ', $candidate->skip_reasons),
                ])->all(),
            );
        }

        if (! $this->option('force')) {
            $this->warn('Nothing was deleted. Run again with --force to permanently delete these accounts.');

            return self::SUCCESS;
        }

        $batch = $limit === null ? $deletable : $deletable->take((int) $limit);
        $failures = 0;

        $progressBar = $this->output->createProgressBar($batch->count());
        $progressBar->start();

        foreach ($batch as $candidate) {
            try {
                $hardDeletionService->deleteAccount($candidate->account_id);
            } catch (Throwable $exception) {
                $failures++;
                $this->newLine();
                $this->error(sprintf('Account %d was not deleted: %s', $candidate->account_id, $exception->getMessage()));
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info(sprintf('Deleted %d account(s).', $batch->count() - $failures));

        $remaining = $deletable->count() - $batch->count();

        if ($remaining > 0) {
            $this->info(sprintf('%d account(s) left. Run the command again to continue.', $remaining));
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function findCandidates(Connection $connection): Collection
    {
        $checks = $this->skipChecks($connection);

        $query = $connection->table('accounts')
            ->join('account_users', 'account_users.account_id', '=', 'accounts.id')
            ->join('users', 'users.id', '=', 'account_users.user_id')
            ->whereIn('accounts.id', self::ACCOUNT_IDS)
            ->where('account_users.is_account_owner', true)
            ->whereNull('accounts.deleted_at')
            ->whereNull('account_users.deleted_at')
            ->whereNull('users.deleted_at')
            ->where('accounts.created_at', '>=', self::SIGNUP_DAY_START)
            ->where('accounts.created_at', '<', self::SIGNUP_DAY_END)
            ->where('users.created_at', '>=', self::SIGNUP_DAY_START)
            ->where('users.created_at', '<', self::SIGNUP_DAY_END)
            ->where('users.timezone', 'like', self::OWNER_TIMEZONE_PREFIX.'%')
            ->select(['accounts.id as account_id', 'users.email'])
            ->orderBy('accounts.id');

        foreach ($checks as $index => [, $check]) {
            $query->selectSub($check->selectRaw('1')->limit(1), "skip_check_$index");
        }

        return $query->get()->map(function (object $candidate) use ($checks): object {
            $reasons = [];

            foreach ($checks as $index => [$reason]) {
                if ($candidate->{"skip_check_$index"} !== null) {
                    $reasons[] = $reason;
                }
            }

            $candidate->skip_reasons = array_values(array_unique($reasons));

            return $candidate;
        });
    }

    /**
     * @return array<int, array{0: string, 1: Builder}>
     */
    private function skipChecks(Connection $connection): array
    {
        return [
            ['Owner belongs to other accounts', $connection->table('account_users as other_memberships')
                ->whereColumn('other_memberships.user_id', 'users.id')
                ->whereColumn('other_memberships.account_id', '!=', 'accounts.id')
                ->whereNull('other_memberships.deleted_at')],
            ['Has other members', $connection->table('account_users as other_members')
                ->whereColumn('other_members.account_id', 'accounts.id')
                ->whereColumn('other_members.user_id', '!=', 'users.id')
                ->whereNull('other_members.deleted_at')],
            ['Has a superadmin member', $connection->table('account_users as superadmin_members')
                ->whereColumn('superadmin_members.account_id', 'accounts.id')
                ->where('superadmin_members.role', Role::SUPERADMIN->value)],
            ['Has orders', $connection->table('orders')
                ->join('events as order_events', 'order_events.id', '=', 'orders.event_id')
                ->whereColumn('order_events.account_id', 'accounts.id')],
            ['Connected Stripe', $connection->table('accounts as stripe_accounts')
                ->whereColumn('stripe_accounts.id', 'accounts.id')
                ->whereNotNull('stripe_accounts.stripe_account_id')],
            ['Connected Stripe', $connection->table('account_stripe_platforms')
                ->whereColumn('account_stripe_platforms.account_id', 'accounts.id')],
            ['Connected Stripe', $connection->table('organizer_stripe_platforms')
                ->join('organizers as stripe_organizers', 'stripe_organizers.id', '=', 'organizer_stripe_platforms.organizer_id')
                ->whereColumn('stripe_organizers.account_id', 'accounts.id')],
            ['Created events after 2026-09-10', $connection->table('events as later_events')
                ->whereColumn('later_events.account_id', 'accounts.id')
                ->where('later_events.created_at', '>=', self::SIGNUP_DAY_END)],
        ];
    }
}
