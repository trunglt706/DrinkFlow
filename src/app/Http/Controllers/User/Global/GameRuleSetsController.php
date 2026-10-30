<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Global;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\SaveGameRuleSetRequest;
use App\Models\Game;
use App\Models\GameRuleSet;
use App\Services\Game\GameRuleSetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Owner's scoring rule sets of a game. Saving an existing set writes a new version.
 */
class GameRuleSetsController extends Controller
{
    public function __construct(private readonly GameRuleSetService $ruleSets) {}

    /**
     * List the owner's rule sets for the game.
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @return View Rule sets page.
     */
    public function index(Request $request, Game $game): View
    {
        abort_unless($game->is_active, 404);
        $user = $this->currentGlobalUser($request);

        return view('user.global.games.rule-sets', [
            'user' => $user,
            'game' => $game,
            'ruleSets' => $this->ruleSets->forOwner($game, $user),
            'breadcrumbs' => $this->breadcrumbs($game, ['label' => __('games.rules.title'), 'url' => route('user.me.games.rule-sets.index', $game)]),
        ]);
    }

    /**
     * New rule set form, prefilled with one default item.
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @return View Form page.
     */
    public function create(Request $request, Game $game): View
    {
        abort_unless($game->is_active, 404);

        return view('user.global.games.rule-set-form', [
            'user' => $this->currentGlobalUser($request),
            'game' => $game,
            'ruleSet' => null,
            'items' => [['name' => __('games.rules.default_item'), 'points' => 1, 'is_active' => true]],
            'returnToCreate' => $request->boolean('return'),
            'breadcrumbs' => $this->breadcrumbs($game, ['label' => __('games.rules.create_title'), 'url' => route('user.me.games.rule-sets.create', $game)]),
        ]);
    }

    /**
     * Create the rule set (version 1).
     *
     * @param SaveGameRuleSetRequest $request Validated form.
     * @param Game $game Game.
     * @return RedirectResponse Rule sets list, or the create-room form when coming from it.
     */
    public function store(SaveGameRuleSetRequest $request, Game $game): RedirectResponse
    {
        abort_unless($game->is_active, 404);
        $this->ruleSets->create(
            $game,
            $this->currentGlobalUser($request),
            (string) $request->validated('name'),
            (bool) $request->validated('allow_negative_points'),
            $request->items(),
        );

        $target = $request->boolean('return') ? route('user.me.games.rooms.create', $game) : route('user.me.games.rule-sets.index', $game);

        return redirect($target)->with('status', __('games.flash.rule_set_created'));
    }

    /**
     * Edit form of a rule set, prefilled with its current version.
     *
     * @param Request $request Current request.
     * @param Game $game Game.
     * @param GameRuleSet $ruleSet Rule set (scoped to the game).
     * @return View Form page.
     */
    public function edit(Request $request, Game $game, GameRuleSet $ruleSet): View
    {
        $user = $this->currentGlobalUser($request);
        Gate::forUser($user)->authorize('manage', $ruleSet);
        $ruleSet->load('currentVersion.items');

        return view('user.global.games.rule-set-form', [
            'user' => $user,
            'game' => $game,
            'ruleSet' => $ruleSet,
            'items' => $ruleSet->currentVersion?->items
                ->map(static fn ($item): array => ['name' => $item->name, 'points' => $item->points, 'is_active' => $item->is_active])
                ->all() ?? [],
            'returnToCreate' => false,
            'breadcrumbs' => $this->breadcrumbs($game, ['label' => $ruleSet->name, 'url' => route('user.me.games.rule-sets.edit', [$game, $ruleSet])]),
        ]);
    }

    /**
     * Save the rule set as a new version.
     *
     * @param SaveGameRuleSetRequest $request Validated form (owner checked).
     * @param Game $game Game.
     * @param GameRuleSet $ruleSet Rule set.
     * @return RedirectResponse Rule sets list.
     */
    public function update(SaveGameRuleSetRequest $request, Game $game, GameRuleSet $ruleSet): RedirectResponse
    {
        $version = $this->ruleSets->revise(
            $ruleSet,
            (string) $request->validated('name'),
            (bool) $request->validated('allow_negative_points'),
            $request->items(),
        );

        return redirect()->route('user.me.games.rule-sets.index', $game)
            ->with('status', __('games.flash.rule_set_versioned', ['version' => $version->version]));
    }

    /**
     * Breadcrumbs: personal space › games › game › last.
     *
     * @param Game $game Game.
     * @param array{label: string, url: string} $last Last crumb.
     * @return array<int, array{label: string, url: string}> Breadcrumbs.
     */
    private function breadcrumbs(Game $game, array $last): array
    {
        return [
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('games.page_title'), 'url' => route('user.me.games')],
            ['label' => $game->name, 'url' => route('user.me.games.show', $game)],
            $last,
        ];
    }
}
