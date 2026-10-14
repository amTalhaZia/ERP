import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MotionConfig } from 'framer-motion';
import { useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable';
import { MenuItem } from '@/components/ui/Menu';

interface Row {
  id: number;
  name: string;
  total: string;
}

const rows: Row[] = [
  { id: 1, name: 'Alpha', total: '10.00' },
  { id: 2, name: 'Beta', total: '20.00' },
];

const columns: DataTableColumn<Row>[] = [
  { accessorKey: 'name', header: 'Name', enableSorting: true },
  { accessorKey: 'total', header: 'Total' },
];

function Controlled({ onSortChange }: { onSortChange: (s: string | undefined) => void }) {
  const [sort, setSort] = useState<string | undefined>();
  return (
    <DataTable
      aria-label="Invoices"
      columns={columns}
      data={rows}
      sort={sort}
      onSortChange={(s) => {
        onSortChange(s);
        setSort(s);
      }}
    />
  );
}

describe('DataTable', () => {
  it('renders a row per record', () => {
    render(<DataTable aria-label="Invoices" columns={columns} data={rows} />);
    const table = screen.getByRole('table', { name: 'Invoices' });
    const bodyRows = within(table).getAllByRole('row').slice(1);
    expect(bodyRows).toHaveLength(2);
    expect(within(bodyRows[0]).getByText('Alpha')).toBeInTheDocument();
    expect(within(bodyRows[1]).getByText('20.00')).toBeInTheDocument();
  });

  it('shows a custom empty state when there are no rows', () => {
    render(<DataTable columns={columns} data={[]} emptyState={<p>No invoices yet</p>} />);
    expect(screen.getByText('No invoices yet')).toBeInTheDocument();
  });

  it('shows the default empty state when data is undefined', () => {
    render(<DataTable columns={columns} data={undefined} />);
    expect(screen.getByText('Nothing here yet')).toBeInTheDocument();
  });

  it('header click toggles sort asc → desc ("-field") → none', async () => {
    const user = userEvent.setup();
    const onSortChange = vi.fn();
    render(<Controlled onSortChange={onSortChange} />);
    const header = screen.getByRole('button', { name: 'Name' });
    expect(header.closest('th')).toHaveAttribute('aria-sort', 'none');

    await user.click(header);
    expect(onSortChange).toHaveBeenLastCalledWith('name');
    expect(header.closest('th')).toHaveAttribute('aria-sort', 'ascending');

    await user.click(header);
    expect(onSortChange).toHaveBeenLastCalledWith('-name');
    expect(header.closest('th')).toHaveAttribute('aria-sort', 'descending');

    await user.click(header);
    expect(onSortChange).toHaveBeenLastCalledWith(undefined);
  });

  it('non-sortable columns render no sort button', () => {
    render(<DataTable columns={columns} data={rows} onSortChange={() => {}} />);
    expect(screen.queryByRole('button', { name: 'Total' })).not.toBeInTheDocument();
  });

  it('opens the row action menu from the keyboard and selects an item', async () => {
    const user = userEvent.setup();
    const onEdit = vi.fn();
    render(
      <MotionConfig reducedMotion="always">
        <DataTable columns={columns} data={rows} rowActions={(row) => <MenuItem onSelect={() => onEdit(row.id)}>Edit</MenuItem>} />
      </MotionConfig>,
    );
    const triggers = screen.getAllByRole('button', { name: 'Row actions' });
    expect(triggers).toHaveLength(2);
    triggers[1].focus();
    await user.keyboard('{ArrowDown}');
    expect(triggers[1]).toHaveAttribute('aria-expanded', 'true');
    const item = await screen.findByRole('menuitem', { name: 'Edit' });
    await user.click(item);
    expect(onEdit).toHaveBeenCalledWith(2);
  });
});
