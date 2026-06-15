using System.Net.Mail;
using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.ValueObjects;

public sealed class Email : ValueObject
{
    public const int MaxLength = 300;

    private Email(string value) => Value = value;

    public string Value { get; }

    public static Email Create(string value)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(value);

        string normalized = value.Trim().ToLowerInvariant();
        ArgumentOutOfRangeException.ThrowIfGreaterThan(normalized.Length, MaxLength);

        if (!MailAddress.TryCreate(normalized, out _))
        {
            throw new ArgumentException($"'{value}' is not a valid email address.", nameof(value));
        }

        return new Email(normalized);
    }

    public override string ToString() => Value;

    protected override IEnumerable<object?> GetEqualityComponents()
    {
        yield return Value;
    }
}
