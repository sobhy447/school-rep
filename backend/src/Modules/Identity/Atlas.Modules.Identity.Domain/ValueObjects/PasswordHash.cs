using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.ValueObjects;

public sealed class PasswordHash : ValueObject
{
    public const int MaxLength = 1000;

    private PasswordHash(string value) => Value = value;

    public string Value { get; }

    public static PasswordHash Create(string value)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(value);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(value.Length, MaxLength);

        return new PasswordHash(value);
    }

    public override string ToString() => Value;

    protected override IEnumerable<object?> GetEqualityComponents()
    {
        yield return Value;
    }
}
