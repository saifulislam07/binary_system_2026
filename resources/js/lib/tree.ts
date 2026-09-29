import type { InjectionKey, Ref } from 'vue';

/** One member in the placement tree (see App\Services\TeamService::subtree()). */
export type TreeNodeData = {
    code: string | null;
    name: string;
    status: string;
    active: boolean;
    package: string | null;
    rank: string | null;
    joined: string | null;
    side: string | null;
    leftBv: number;
    rightBv: number;
    teamBv: number;
    hasLeft: boolean;
    hasRight: boolean;
    children: { left: TreeNodeData | null; right: TreeNodeData | null } | null;
};

/** Provided by the team page; tree nodes report clicks through it. */
export type TreeContext = {
    selected: Ref<TreeNodeData | null>;
    select: (node: TreeNodeData) => void;
};

export const treeContextKey: InjectionKey<TreeContext> = Symbol('tree');
