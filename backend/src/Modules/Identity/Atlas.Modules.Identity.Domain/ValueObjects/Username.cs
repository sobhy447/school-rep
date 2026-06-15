using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.ValueObjects;

public sealed class Username : ValueObject
{
    public const int MaxLength = 100;

    private Username(string value) => Value = value;

    public string Value { get; }

    public static Username Create(string value)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(value);

        string trimmed = value.Trim();
        ArgumentOutOfRangeException.ThrowIfGreaterThan(trimmed.Length, MaxLength);

        return new Username(trimmed);
    }

    public override string ToString() => Value;

    protected override IEnumerable<object?> GetEqualityComponents()
    {
        yield return Value;
    }
}
