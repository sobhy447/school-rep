using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Tenancy.Domain.ValueObjects;

public sealed class Address : ValueObject
{
    public const int LineMaxLength = 500;
    public const int CityMaxLength = 200;
    public const int CountryCodeMaxLength = 3;

    private Address(string? line1, string? line2, string? city, string countryCode)
    {
        Line1 = line1;
        Line2 = line2;
        City = city;
        CountryCode = countryCode;
    }

    public string? Line1 { get; }

    public string? Line2 { get; }

    public string? City { get; }

    public string CountryCode { get; }

    public static Address Create(string? line1, string? line2, string? city, string countryCode)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(countryCode);

        string normalizedCountry = countryCode.Trim().ToUpperInvariant();
        ArgumentOutOfRangeException.ThrowIfGreaterThan(normalizedCountry.Length, CountryCodeMaxLength);

        string? normalizedLine1 = Normalize(line1, LineMaxLength);
        string? normalizedLine2 = Normalize(line2, LineMaxLength);
        string? normalizedCity = Normalize(city, CityMaxLength);

        return new Address(normalizedLine1, normalizedLine2, normalizedCity, normalizedCountry);
    }

    private static string? Normalize(string? value, int maxLength)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return null;
        }

        string trimmed = value.Trim();
        ArgumentOutOfRangeException.ThrowIfGreaterThan(trimmed.Length, maxLength);
        return trimmed;
    }

    protected override IEnumerable<object?> GetEqualityComponents()
    {
        yield return Line1;
        yield return Line2;
        yield return City;
        yield return CountryCode;
    }
}
