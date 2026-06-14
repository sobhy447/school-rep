namespace Atlas.SharedKernel.Primitives;

public abstract class ValueObject : IEquatable<ValueObject>
{
    protected abstract IEnumerable<object?> GetEqualityComponents();

    public bool Equals(ValueObject? other) =>
        other is not null && ValuesAreEqual(other);

    public override bool Equals(object? obj) =>
        obj is ValueObject valueObject && ValuesAreEqual(valueObject);

    public override int GetHashCode() =>
        GetEqualityComponents()
            .Aggregate(default(HashCode), (hash, component) =>
            {
                hash.Add(component);
                return hash;
            })
            .ToHashCode();

    public static bool operator ==(ValueObject? left, ValueObject? right) =>
        Equals(left, right);

    public static bool operator !=(ValueObject? left, ValueObject? right) =>
        !Equals(left, right);

    private bool ValuesAreEqual(ValueObject other) =>
        GetType() == other.GetType() &&
        GetEqualityComponents().SequenceEqual(other.GetEqualityComponents());
}
