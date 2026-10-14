import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { FormField } from '@/components/ui/FormField';
import { Input } from '@/components/ui/Input';

describe('FormField', () => {
  it('wires label and hint to the control', () => {
    render(
      <FormField label="Email" id="email" hint="Your work email" required>
        <Input />
      </FormField>,
    );
    const input = screen.getByLabelText(/Email/);
    expect(input).toHaveAttribute('id', 'email');
    expect(input).toHaveAttribute('aria-describedby', 'email-hint');
    expect(input).toBeRequired();
    expect(input).not.toHaveAttribute('aria-invalid');
    expect(input).toHaveAccessibleDescription('Your work email');
  });

  it('marks the control invalid and describes it with the error (hint hidden)', () => {
    render(
      <FormField label="Email" id="email" hint="Your work email" error="Email is required">
        <Input />
      </FormField>,
    );
    const input = screen.getByLabelText('Email');
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAttribute('aria-describedby', 'email-error');
    expect(input).toHaveAccessibleDescription('Email is required');
    expect(screen.getByRole('alert')).toHaveTextContent('Email is required');
    expect(screen.queryByText('Your work email')).not.toBeInTheDocument();
  });

  it('generates an id and supports the render-prop form with keepHint', () => {
    render(
      <FormField label="Amount" error="Too large" hint="Max 100" keepHint>
        {(p) => <input {...p} />}
      </FormField>,
    );
    const input = screen.getByLabelText('Amount');
    expect(input.id).toBeTruthy();
    expect(input.getAttribute('aria-describedby')).toBe(`${input.id}-hint ${input.id}-error`);
  });
});
