<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Link;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    protected function rules(): array
    {
        return [
            'title' => 'string|min:3',
            'parentGroupId' => 'exists:App\Models\Group,id|numeric|nullable'
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('Groups/Index', [
            'groups' => Group::filterByCurrentUser()
                ->orderBy('title')
                ->where('parent_group_id', null)
                ->withCount(['links', 'groups'])
                ->get()
                ->transform(fn(Group $group) => [
                    'id' => $group->id,
                    'title' => $group->title,
                    'childGroupsCount' => $group->groups_count,
                    'linksCount' => $group->links_count,
                ]),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Request::validate($this->rules());

        $group = Group::make();

        $group->title = Request::get('title');
        $group->parent_group_id = Request::get('parentGroupId');
        $group->user_id = Auth::id();

        $group->save();
    }

    /**
     * Display the specified resource.
     */
    public function show(int $groupId): Response|RedirectResponse
    {
        $group = Group::filterByCurrentUser()->find($groupId);

        if ($group === null) {
            return Redirect::route('groups.index');
        }

        return Inertia::render('SingleGroup/Index', [
            'group' => (object)[
                'title' => $group->title,
                'id' => $group->id,
                'parentGroupId' => $group->parent_group_id,
            ],
            'groups' => Group::filterByCurrentUser()
                ->orderBy('title')
                ->where('parent_group_id', $groupId)
                ->withCount(['links', 'groups'])
                ->get()
                ->transform(fn(Group $group) => [
                    'id' => $group->id,
                    'title' => $group->title,
                    'childGroupsCount' => $group->groups_count,
                    'linksCount' => $group->links_count,
                ]),
            'parentGroups' => $this->getAllParentGroups($group),
            'links' => $group
                ->links()
                ->orderBy('created_at', 'desc')
                ->paginate(20)
                ->through(fn(Link $link) => [
                    'title' => $link->title,
                    'link' => $link->link,
                    'id' => $link->id,
                ]),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Group $group)
    {
        Request::validate($this->rules());

        $group->title = Request::get('title');

        // The parent group cannot be itself.
        if ($group->id !== Request::get('parentGroupId')) {
            $group->parent_group_id = Request::get('parentGroupId');
        }

        $group->save();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Group $group)
    {
        $group->delete();
    }

    protected function getAllParentGroups(Group $group): array|null
    {
        $parentGroups = [];
        $currentGroupInLoop = $group;

        while (($currentGroupInLoop = $this->getParentGroup($currentGroupInLoop)) !== null) {
            $parentGroups[] = (object)[
                'title' => $currentGroupInLoop->title,
                'link' => route('groups.show', $currentGroupInLoop->id, absolute: false),
            ];
        }

        return array_reverse($parentGroups);
    }

    protected function getParentGroup(Group $group): Group|null
    {
        if ($group->parent_group_id === null) {
            return null;
        }

        return Group::filterByCurrentUser()->find($group->parent_group_id);
    }

    /**
     * Returns all groups for the current user.
     */
    public function getAllGroups()
    {
        return Group::orderBy('title')
            ->filterByCurrentUser()
            ->withCount('groups')
            ->get()
            ->transform(fn(Group $group) => [
                'id' => $group->id,
                'title' => $group->title,
                'parentGroupId' => $group->parent_group_id,
                'childGroupsCount' => $group->groups_count,
            ]);
    }
}
