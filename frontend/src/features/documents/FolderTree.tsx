'use client';

import { useMemo, useState } from 'react';
import { ChevronRight, Folder, FolderOpen, FolderPlus, Inbox, Lock, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import {
  Alert, Button, ConfirmDialog, ErrorState, FormField, IconButton, Input, Menu, MenuItem, MenuSeparator, Modal, Select, Skeleton, Switch,
} from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { documentsApi, useDocumentsMutation, useFolders, type DocumentFolder } from './api';
import { folderOptions } from './DocumentForm';
import styles from './documents.module.scss';

interface Node extends DocumentFolder {
  children: Node[];
}

function buildTree(folders: DocumentFolder[]): Node[] {
  const map = new Map<number, Node>();
  for (const f of folders) map.set(f.id, { ...f, children: [] });
  const roots: Node[] = [];
  for (const n of map.values()) {
    const parent = n.parent_id ? map.get(n.parent_id) : undefined;
    if (parent) parent.children.push(n);
    else roots.push(n);
  }
  const sort = (list: Node[]) => {
    list.sort((a, b) => a.name.localeCompare(b.name));
    list.forEach((n) => sort(n.children));
  };
  sort(roots);
  return roots;
}

/** Ids of `id` and all its ancestors. */
function ancestors(folders: DocumentFolder[], id: number | null): Set<number> {
  const out = new Set<number>();
  let cur = id ? folders.find((f) => f.id === id) : undefined;
  let guard = 0;
  while (cur && guard++ < 50) {
    out.add(cur.id);
    cur = cur.parent_id ? folders.find((f) => f.id === cur!.parent_id) : undefined;
  }
  return out;
}

export function FolderTree({ selected, onSelect }: { selected: number | null; onSelect: (id: number | null) => void }) {
  const can = useCan();
  const canManage = can('documents.manage');
  const q = useFolders();
  const folders = useMemo(() => q.data ?? [], [q.data]);
  const tree = useMemo(() => buildTree(folders), [folders]);
  const [expanded, setExpanded] = useState<Set<number>>(() => new Set());
  const [mobileOpen, setMobileOpen] = useState(false);
  const [editing, setEditing] = useState<DocumentFolder | { parent_id: number | null } | null>(null);
  const [deleting, setDeleting] = useState<DocumentFolder | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const del = useDocumentsMutation((id: number) => documentsApi.deleteFolder(id), { success: 'Folder deleted', errorTitle: 'Could not delete folder' });

  // The selected folder's ancestors are always open so the selection stays visible.
  const open = useMemo(() => {
    const s = new Set(expanded);
    ancestors(folders, selected).forEach((id) => id !== selected && s.add(id));
    return s;
  }, [expanded, folders, selected]);

  const toggle = (id: number) =>
    setExpanded((prev) => {
      const next = new Set(prev);
      if (open.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  const selectedName = selected ? folders.find((f) => f.id === selected)?.name ?? 'Folder' : 'All documents';

  const renderNode = (n: Node) => {
    const isOpen = open.has(n.id);
    const active = selected === n.id;
    return (
      <li key={n.id} role="treeitem" aria-expanded={n.children.length ? isOpen : undefined} aria-selected={active}>
        <div className={styles.node} data-active={active}>
          {n.children.length ? (
            <button type="button" className={styles.caret} aria-expanded={isOpen} aria-label={`${isOpen ? 'Collapse' : 'Expand'} ${n.name}`} onClick={() => toggle(n.id)}>
              <ChevronRight size={14} />
            </button>
          ) : (
            <span className={styles.caretSpacer} />
          )}
          <button type="button" className={styles.nodeLabel} onClick={() => { onSelect(n.id); setMobileOpen(false); }} aria-current={active || undefined}>
            {active ? <FolderOpen size={15} aria-hidden /> : <Folder size={15} aria-hidden />}
            <span>{n.name}</span>
            {n.is_private && <Lock size={12} className={styles.privateIcon} aria-label="Private folder" />}
            {n.documents_count !== undefined && <span className={styles.nodeCount}>{n.documents_count}</span>}
          </button>
          {canManage && (
            <Menu align="end" trigger={<IconButton size="sm" variant="ghost" icon={<MoreHorizontal size={14} />} aria-label={`Folder actions for ${n.name}`} />}>
              <MenuItem icon={<FolderPlus size={15} />} onSelect={() => setEditing({ parent_id: n.id })}>New subfolder</MenuItem>
              <MenuItem icon={<Pencil size={15} />} onSelect={() => setEditing(n)}>Rename / move</MenuItem>
              <MenuSeparator />
              <MenuItem icon={<Trash2 size={15} />} destructive onSelect={() => { setDeleteError(null); setDeleting(n); }}>Delete</MenuItem>
            </Menu>
          )}
        </div>
        {n.children.length > 0 && isOpen && <ul role="group">{n.children.map(renderNode)}</ul>}
      </li>
    );
  };

  return (
    <nav className={styles.treeCard} aria-label="Folders">
      <button type="button" className={styles.treeToggle} aria-expanded={mobileOpen} onClick={() => setMobileOpen((o) => !o)}>
        <FolderOpen size={16} aria-hidden />
        <span style={{ flex: 1, textAlign: 'left' }}>{selectedName}</span>
        <ChevronRight size={14} style={{ transform: mobileOpen ? 'rotate(90deg)' : undefined }} aria-hidden />
      </button>
      <div className={styles.treeBody} data-collapsed={!mobileOpen}>
        <div className={styles.treeHeader}>
          <span>Folders</span>
          {canManage && <IconButton size="sm" variant="ghost" icon={<FolderPlus size={15} />} aria-label="New folder" onClick={() => setEditing({ parent_id: null })} />}
        </div>
        {q.error ? (
          <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} />
        ) : q.isLoading ? (
          <Skeleton lines={5} />
        ) : (
          <ul className={styles.tree} role="tree" aria-label="Document folders">
            <li role="treeitem" aria-selected={selected === null}>
              <div className={styles.node} data-active={selected === null}>
                <span className={styles.caretSpacer} />
                <button type="button" className={styles.nodeLabel} onClick={() => { onSelect(null); setMobileOpen(false); }} aria-current={selected === null || undefined}>
                  <Inbox size={15} aria-hidden />
                  <span>All documents</span>
                </button>
              </div>
            </li>
            {tree.map(renderNode)}
            {tree.length === 0 && <li className={styles.note} style={{ padding: 8 }}>No folders yet.</li>}
          </ul>
        )}
      </div>

      {editing && (
        <FolderModal
          key={'id' in editing ? `f-${editing.id}` : `new-${editing.parent_id ?? 'root'}`}
          folder={'id' in editing ? editing : null}
          parentId={editing.parent_id}
          folders={folders}
          onClose={() => setEditing(null)}
          onSaved={(f) => {
            if (f.parent_id) setExpanded((prev) => new Set(prev).add(f.parent_id!));
          }}
        />
      )}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete folder “${deleting?.name}”?`}
        description={deleteError ?? 'Only empty folders can be deleted. Documents and subfolders must be moved first.'}
        destructive
        confirmLabel="Delete folder"
        onConfirm={async () => {
          if (!deleting) return;
          try {
            await del.mutateAsync(deleting.id);
            if (selected === deleting.id) onSelect(null);
            setDeleting(null);
          } catch (e) {
            setDeleteError((e as ApiError).message);
          }
        }}
      />
    </nav>
  );
}

function FolderModal({
  folder,
  parentId,
  folders,
  onClose,
  onSaved,
}: {
  folder: DocumentFolder | null;
  parentId: number | null;
  folders: DocumentFolder[];
  onClose: () => void;
  onSaved: (f: DocumentFolder) => void;
}) {
  const [name, setName] = useState(folder?.name ?? '');
  const [parent, setParent] = useState(parentId ? String(parentId) : '');
  const [isPrivate, setIsPrivate] = useState(folder?.is_private ?? false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const save = useDocumentsMutation(
    () => {
      const body = { name: name.trim(), parent_id: parent ? Number(parent) : null, is_private: isPrivate };
      return folder ? documentsApi.updateFolder(folder.id, body) : documentsApi.createFolder(body);
    },
    { success: folder ? 'Folder saved' : 'Folder created', errorTitle: 'Could not save folder' },
  );

  const submit = async () => {
    setErrors({});
    setMessage(null);
    if (!name.trim()) {
      setErrors({ name: 'Name is required' });
      return;
    }
    try {
      const f = await save.mutateAsync(undefined);
      onSaved(f);
      onClose();
    } catch (e) {
      const err = e as ApiError;
      if (err.status === 422 && err.errors) {
        setErrors(Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0] ?? 'Invalid'])));
      } else {
        setMessage(err.message);
      }
    }
  };

  // A folder can't be moved under itself or its descendants.
  const excluded = new Set<number>();
  if (folder) {
    const walk = (id: number) => {
      excluded.add(id);
      folders.filter((f) => f.parent_id === id).forEach((c) => walk(c.id));
    };
    walk(folder.id);
  }
  const parentOptions = folderOptions(folders).filter((o) => !excluded.has(Number(o.value)));

  return (
    <Modal
      open
      onClose={onClose}
      size="sm"
      title={folder ? `Edit folder` : 'New folder'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button onClick={() => void submit()} loading={save.isPending}>{folder ? 'Save' : 'Create folder'}</Button>
        </>
      }
    >
      <form onSubmit={(e) => { e.preventDefault(); void submit(); }} className={styles.stack}>
        {message && <Alert variant="danger">{message}</Alert>}
        <FormField label="Name" required error={errors.name}>
          <Input value={name} maxLength={150} onChange={(e) => setName(e.target.value)} autoFocus />
        </FormField>
        <FormField label="Parent folder" error={errors.parent_id}>
          <Select value={parent} onChange={(e) => setParent(e.target.value)} options={[{ value: '', label: 'Top level' }, ...parentOptions]} />
        </FormField>
        <Switch checked={isPrivate} onCheckedChange={setIsPrivate} label="Private folder" description="Only you can see a private folder and browse into it." />
        <button type="submit" hidden />
      </form>
    </Modal>
  );
}
