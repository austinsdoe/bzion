<?php

use Symfony\Bundle\FrameworkBundle\Client;

// Notes:
//  - URLs are hard coded in this class to ensure that if they change, there are appropriate redirects in place
//  - These POST requests are following the spec defined here: https://github.com/allejo/LeagueOverseer#match-reports

class LeagueOverseerHookTest extends TestCase
{
    /** @var Player */
    private $player_a, $player_b, $player_c, $player_d;
    /** @var Team */
    private $team_a, $team_b;
    /** @var Client */
    private $client;

    public function setUp()
    {
        $this->connectToDatabase();
        $this->client = self::createClient();

        $this->player_a = $this->getNewPlayer();
        $this->player_b = $this->getNewPlayer();
        $this->player_c = $this->getNewPlayer();
        $this->player_d = $this->getNewPlayer();

        $this->team_a = Team::createTeam('Team A', $this->player_a->getId(), '', '');
        $this->team_b = Team::createTeam('Team B', $this->player_b->getId(), '', '');
    }

    public function tearDown()
    {
        $this->wipeMatches();
        $this->wipe($this->team_a, $this->team_b);

        parent::tearDown();
    }

    public function testTeamVsTeamReport_TeamAWin()
    {
        $this->team_a->addMember($this->player_c->getId());
        $this->team_b->addMember($this->player_d->getId());

        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 5,
            'teamTwoWins' => 4,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]),
        ]));

        $this->assertEquals("(+/- 25) Team A [5] vs [4] Team B\n  player elo: +/- 25", $this->client->getResponse()->getContent());
    }

    public function testTeamVsTeamReport_TeamBWin()
    {
        $this->team_a->addMember($this->player_c->getId());
        $this->team_b->addMember($this->player_d->getId());

        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 0,
            'teamTwoWins' => 4,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]),
        ]));

        $this->assertEquals("(+/- 25) Team B [4] vs [0] Team A\n  player elo: +/- 25", $this->client->getResponse()->getContent());
    }

    /**
     * Despite the players being Team A vs Team B, we reported it as an FM and therefore should be treated as such
     */
    public function testTeamVsTeamFmReport()
    {
        $this->team_a->addMember($this->player_c->getId());
        $this->team_b->addMember($this->player_d->getId());

        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'matchType' => 'fm',
            'teamOneWins' => 5,
            'teamTwoWins' => 4,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]),
        ]));

        $this->assertEquals("Fun Match: Red Team [5] vs [4] Purple Team", $this->client->getResponse()->getContent());
    }

    public function testTeamVsMixedReport_TeamLoss()
    {
        $this->team_a->addMember($this->player_c->getId());

        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 3,
            'teamTwoWins' => 5,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]), // player D is teamless
        ]));

        $this->assertEquals("Purple Team [5] vs [3] Team A\n  Team A: -25\n  player elo: +/- 25", $this->client->getResponse()->getContent());

        $this->assertEquals(1175, $this->team_a->getElo());
        $this->assertEquals(1200, $this->team_b->getElo());
        $this->assertEquals(1175, $this->player_a->getElo());
        $this->assertEquals(1225, $this->player_d->getElo());
    }

    public function testTeamVsMixedReport_TeamWin()
    {
        $this->team_a->addMember($this->player_c->getId());

        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 1,
            'teamTwoWins' => 0,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]), // player D is teamless
        ]));

        $this->assertEquals("Team A [1] vs [0] Purple Team\n  Team A: +25\n  player elo: +/- 25", $this->client->getResponse()->getContent());

        $this->assertEquals(1225, $this->team_a->getElo());
        $this->assertEquals(1200, $this->team_b->getElo());
    }

    public function testTeamVsMixedReport_Draw_TeamHasLower()
    {
        $this->team_a->addMember($this->player_c->getId());

        $this->player_b->adjustElo(200);
        $this->player_d->adjustElo(200);

        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 2,
            'teamTwoWins' => 2,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]), // player D is teamless
        ]));

        $this->assertEquals("Team A [2] vs [2] Purple Team\n  Team A: +12\n  player elo: +/- 12", $this->client->getResponse()->getContent());

        $this->assertEquals(1212, $this->team_a->getElo());
    }

    public function testTeamVsMixedReport_Draw_MixedHasLower()
    {
        $this->team_a->addMember($this->player_c->getId());

        $this->player_a->adjustElo(200);
        $this->player_c->adjustElo(200);

        $this->cacheModel($this->player_a);
        $this->cacheModel($this->player_c);

        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 2,
            'teamTwoWins' => 2,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]), // player D is teamless
        ]));

        $this->assertEquals("Purple Team [2] vs [2] Team A\n  Team A: -12\n  player elo: +/- 12", $this->client->getResponse()->getContent());

        $this->assertEquals(1188, $this->team_a->getElo());
    }

    public function testMixedVsMixedReport_FirstTeamWin()
    {
        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 4,
            'teamTwoWins' => 1,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]),
        ]));

        $this->assertEquals("Red Team [4] vs [1] Purple Team\n  player elo: +/- 25", $this->client->getResponse()->getContent());
    }

    public function testMixedVsMixedReport_SecondTeamWin()
    {
        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 0,
            'teamTwoWins' => 3,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]),
        ]));

        $this->assertEquals("Purple Team [3] vs [0] Red Team\n  player elo: +/- 25", $this->client->getResponse()->getContent());
    }

    public function testMixedVsMixedReport_Draw_TeamAHasLowerPlayerElo()
    {
        $this->player_c->adjustElo(100);
        $this->player_d->adjustElo(500);

        $teamA_playerElo = ($this->player_a->getElo() + $this->player_c->getElo()) / 2;
        $teamB_playerElo = ($this->player_b->getElo() + $this->player_d->getElo()) / 2;

        $playerElo = Match::calculateEloDiff($teamA_playerElo, $teamB_playerElo, 1, 1, 1800);

        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 1,
            'teamTwoWins' => 1,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]),
        ]));

        $this->assertEquals("Red Team [1] vs [1] Purple Team\n  player elo: +/- {$playerElo}", $this->client->getResponse()->getContent());

        // This team had a combined _lower_ Elo, so they'll benefit from this match
        $this->assertEquals((1200 + $playerElo), $this->player_a->getElo());
        $this->assertEquals((1300 + $playerElo), $this->player_c->getElo());

        // This team had a combined _higher_ Elo, so they'll take the penalty
        $this->assertEquals((1200 - $playerElo), $this->player_b->getElo());
        $this->assertEquals((1700 - $playerElo), $this->player_d->getElo());
    }

    public function testMixedVsMixedReport_Draw_TeamBHasLowerPlayerElo()
    {
        $this->player_a->adjustElo(300);
        $this->player_c->adjustElo(500);
        $this->player_d->adjustElo(100);

        $teamA_playerElo = ($this->player_a->getElo() + $this->player_c->getElo()) / 2;
        $teamB_playerElo = ($this->player_b->getElo() + $this->player_d->getElo()) / 2;

        $playerElo = abs(Match::calculateEloDiff($teamA_playerElo, $teamB_playerElo, 1, 1, 1800));

        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 1,
            'teamTwoWins' => 1,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_b->getBZID(), $this->player_d->getBZID()]),
        ]));

        $this->assertEquals("Purple Team [1] vs [1] Red Team\n  player elo: +/- {$playerElo}", $this->client->getResponse()->getContent());

        // This team had a combined _higher_ Elo, so they'll take the penalty
        $this->assertEquals(1500 - $playerElo, $this->player_a->getElo());
        $this->assertEquals(1700 - $playerElo, $this->player_c->getElo());

        /// This team had a combined _lower_ Elo, so they'll benefit here
        $this->assertEquals(1200 + $playerElo, $this->player_b->getElo());
        $this->assertEquals(1300 + $playerElo, $this->player_d->getElo());
    }

    /**
     * The same team playing against each other in an official match is disallowed; e.g. Team A vs Team A
     */
    public function testSameTeamVsReport()
    {
        $player_e = $this->getNewPlayer();

        $this->team_a->addMember($this->player_c->getId());
        $this->team_a->addMember($this->player_d->getId());
        $this->team_a->addMember($player_e->getId());

        $this->client->request('POST', '/api/leagueOverseer', self::defaultPOST([
            'teamOneWins' => 3,
            'teamTwoWins' => 5,
            'teamOnePlayers' => implode(',', [$this->player_a->getBZID(), $this->player_c->getBZID()]),
            'teamTwoPlayers' => implode(',', [$this->player_d->getBZID(), $player_e->getBZID()]), // player D is teamless
        ]));

        $this->assertContains('Holy sanity check, Batman!', $this->client->getResponse()->getContent());
    }

    /**
     * Get the default POST request sent to this API endpoint.
     *
     * Values **not** set by this function:
     *
     *   - teamOneWins
     *   - teamTwoWins
     *   - teamOnePlayers
     *   - teamTwoPlayers
     *
     * @param array $overrides Any POST data that needs to be overwritten
     *
     * @return array
     */
    private static function defaultPOST(array $overrides)
    {
        return array_merge([
            'query' => 'reportMatch',
            'apiVersion' => 1,
            'matchType' => 'official',
            'teamOneColor' => 'Red',
            'teamTwoColor' => 'Purple',
            'duration' => 1800,
            'matchTime' => '17-08-01 13:00:00',
            'server' => 'localhost:5154',
            'port' => 5154,
            'replayFile' => 'my-replay-file.rec',
            'mapPlayed' => 'hix',
            'teamOneIPs' => '127.0.0.1,127.0.0.2',
            'teamTwoIPs' => '127.0.0.3,127.0.0.4',
        ], $overrides);
    }

    /**
     * Helper function to wipe Matches that were created by the controller
     */
    private function wipeMatches()
    {
        /** @var Match[] $matches */
        $matches = Match::getQueryBuilder()
            ->getModels(true);

        foreach ($matches as $match) {
            $match->wipe();
        }
    }
}
