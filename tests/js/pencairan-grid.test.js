import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateHonorTax, calculateRow, formatAmount, normalizeIdentity, parseAmount, parseTsv, toTsv, schemas } from '../../public/js/monita/pencairan-grid.js';

test('Indonesian amounts are parsed exactly to cents; invalid formats are rejected', () => {
    assert.equal(parseAmount('1.250,50'), 125050);
    assert.equal(parseAmount('0'), 0);
    for (const value of ['', '-1', '1,250.50', '12.34', 'NaN', '1e3', '1,234', '10000000001']) {
        assert.throws(() => parseAmount(value));
    }
});

test('Pasted numeric values can be formatted with Indonesian separators', () => {
    assert.equal(formatAmount('150000'), '150.000');
    assert.equal(formatAmount('1250,5'), '1.250,5');
    assert.throws(() => formatAmount('invalid'));
});

test('Impresfund calculates fractional volume and rounds to cents', () => {
    assert.equal(calculateRow('impresfund', { uraian: 'ATK', volume: '2,5', tarif: '1.250,50' }).gross, 312625);
    assert.ok(calculateRow('impresfund', { uraian: 'ATK', volume: '0', tarif: '100' }).errors.volume);
    assert.ok(calculateRow('impresfund', { uraian: 'ATK', volume: '10000000000', tarif: '10000000000' }).errors.tarif);
});

test('Impresfund subtotal overrides automatic calculation and blank restores it', () => {
    const values = { uraian: 'ATK', volume: '2,5', tarif: '1.250,50' };
    const result = calculateRow('impresfund', { ...values, subtotal: '4.000,25' });
    assert.equal(result.gross, 400025);
    assert.equal(result.net, 400025);
    assert.equal(result.tax, 0);
    assert.equal(result.errors, undefined);
    assert.match(result.warnings.subtotal, /seharusnya 3\.126,25/);
    assert.equal(calculateRow('impresfund', { ...values, subtotal: '0' }).gross, 0);
    for (const subtotal of ['', '  ']) {
        assert.equal(calculateRow('impresfund', { ...values, subtotal }).gross, 312625);
    }
    for (const subtotal of ['-1', 'invalid', '1,234', '10000000001']) {
        assert.ok(calculateRow('impresfund', { ...values, subtotal }).errors.subtotal);
    }
    assert.ok(calculateRow('impresfund', { subtotal: '500' }).errors.uraian);
    assert.ok(calculateRow('impresfund', { ...values, volume: '0', subtotal: '500' }).errors.volume);
    assert.equal(calculateRow('impresfund', {
        ...values, volume: '10000000000', tarif: '10000000000', subtotal: '500',
    }).gross, 50000);
    assert.deepEqual(schemas.impresfund.filter((column) => !column.computed).map((column) => column.key),
        ['uraian', 'volume', 'tarif', 'subtotal']);
});

test('Impresfund warns on subtotal mismatches using the same rounded cents as automatic calculation', () => {
    const values = { uraian: 'ATK', volume: '1,5', tarif: '0,01' };
    for (const subtotal of ['0', '0,01', '0,03']) {
        const result = calculateRow('impresfund', { ...values, subtotal });
        assert.equal(result.gross, parseAmount(subtotal));
        assert.equal(result.errors, undefined);
        assert.match(result.warnings.subtotal, /seharusnya 0,02/);
    }
    for (const subtotal of ['', '  ', '0,02']) {
        assert.equal(calculateRow('impresfund', { ...values, subtotal }).warnings, undefined);
    }
    assert.equal(calculateRow('impresfund', { ...values, tarif: '0', subtotal: '0' }).warnings, undefined);
    assert.ok(calculateRow('impresfund', { ...values, uraian: '', subtotal: '0' }).warnings.subtotal);
    for (const changes of [
        { volume: '' }, { volume: 'invalid' }, { volume: '0' }, { tarif: 'invalid' },
        { subtotal: 'invalid' }, { volume: '10000000000', tarif: '10000000000' },
    ]) {
        assert.equal(calculateRow('impresfund', { ...values, subtotal: '1', ...changes }).warnings, undefined);
    }
});

test('Beban calculates after-tax amount by default and allows a manual override', () => {
    const values = { nominal: '100.000', pajak: '2.500', neto: '90.000', bank: 'Bank', nama: 'Nama', rekening: '00123456789012345678' };
    const result = calculateRow('beban', values);
    assert.equal(result.gross, 10000000);
    assert.equal(result.tax, 250000);
    assert.equal(result.net, 9000000);
    assert.match(result.warnings.neto, /seharusnya 97\.500/);
    assert.equal(result.errors, undefined);
    assert.equal(calculateRow('beban', { ...values, nominal: '200.000', pajak: '5.000' }).net, 9000000);
    assert.equal(calculateRow('beban', { ...values, neto: '0' }).net, 0);
    assert.equal(calculateRow('beban', { ...values, neto: '' }).net, 9750000);
    assert.equal(calculateRow('beban', { ...values, nominal: '200.000', pajak: '5.000', neto: '' }).net, 19500000);
    for (const neto of ['-1', 'invalid']) {
        assert.ok(calculateRow('beban', { ...values, neto }).errors.neto);
    }
    assert.ok(calculateRow('beban', { nominal: '100', pajak: '101', bank: 'B', nama: 'N', rekening: '001' }).errors.pajak);
});

test('Beban warns on mismatched net amounts to the cent without blocking manual values', () => {
    const values = { nominal: '1.250,50', pajak: '250,25', bank: 'B', nama: 'N', rekening: '001' };
    for (const neto of ['1.000,24', '1.000,26', '0', '2.000']) {
        const result = calculateRow('beban', { ...values, neto });
        assert.equal(result.net, parseAmount(neto));
        assert.equal(result.errors, undefined);
        assert.match(result.warnings.neto, /seharusnya 1\.000,25/);
    }
    for (const neto of ['', '  ', '1.000,25', '1000,25']) {
        assert.equal(calculateRow('beban', { ...values, neto }).warnings, undefined);
    }
    assert.equal(calculateRow('beban', { nominal: '0', pajak: '0', neto: '0', bank: 'B', nama: 'N', rekening: '001' }).warnings, undefined);
});

test('Beban warnings remain visible on incomplete rows but not invalid numeric operands', () => {
    const result = calculateRow('beban', { nominal: '100', pajak: '10', neto: '80' });
    assert.ok(result.errors.bank);
    assert.match(result.warnings.neto, /seharusnya 90/);
    for (const values of [
        { nominal: '', pajak: '10', neto: '80' },
        { nominal: 'invalid', pajak: '10', neto: '80' },
        { nominal: '100', pajak: 'invalid', neto: '80' },
        { nominal: '100', pajak: '101', neto: '80' },
        { nominal: '100', pajak: '10', neto: 'invalid' },
    ]) {
        assert.ok(calculateRow('beban', values).errors);
        assert.equal(calculateRow('beban', values).warnings, undefined);
    }
});

test('Blank rows are ignored, incomplete rows are invalid, zero tax is allowed', () => {
    assert.equal(calculateRow('beban', {}).empty, true);
    assert.ok(calculateRow('impresfund', { uraian: 'Incomplete' }).errors.volume);
    assert.equal(calculateRow('beban', { nominal: '100', pajak: '0', neto: '100', bank: 'B', nama: 'N', rekening: '001' }).tax, 0);
});

test('Beban clipboard includes editable after-tax amount in table order', () => {
    assert.deepEqual(schemas.beban.filter((column) => !column.computed).map((column) => column.key),
        ['nominal', 'pajak', 'neto', 'bank', 'nama', 'rekening']);
    assert.equal(schemas.beban.find((column) => column.key === 'bank').choiceSource, 'banks');
});

test('Bank initials are validated against active master options', () => {
    const bankOptions = [{ value: 'BRI', active: true }, { value: 'BNI', active: true }];
    const row = { nominal: '100', pajak: '0', neto: '100', bank: 'bri', nama: 'N', rekening: '001' };
    assert.equal(calculateRow('beban', row, { banks: bankOptions }).errors, undefined);
    assert.match(calculateRow('beban', { ...row, bank: 'Bank Rakyat Indonesia' }, { banks: bankOptions }).errors.bank,
        /master data bank aktif/);
    assert.equal(schemas.honor.find((column) => column.key === 'bank').choiceSource, 'banks');
});

test('Tax defaults to zero without activating unused Honor and Beban rows', () => {
    assert.equal(schemas.beban.find((column) => column.key === 'pajak').defaultValue, '0');
    assert.equal(schemas.honor.find((column) => column.key === 'pajak').calculated, true);
    assert.equal(schemas.honor.find((column) => column.key === 'npwp').defaultValue, '00.000.000.0-000.000');
    assert.deepEqual(calculateRow('honor', { pajak: '0' }), { empty: true, gross: 0, tax: 0, net: 0 });
    assert.deepEqual(calculateRow('honor', { npwp: '00.000.000.0-000.000' }), { empty: true, gross: 0, tax: 0, net: 0 });
    assert.deepEqual(calculateRow('honor', { pajak: '1' }), { empty: true, gross: 0, tax: 0, net: 0 });
    assert.deepEqual(calculateRow('beban', { pajak: '0' }), { empty: true, gross: 0, tax: 0, net: 0 });
    assert.ok(calculateRow('beban', { pajak: '1' }).errors.nominal);
    assert.ok(calculateRow('beban', { pajak: '0', nominal: '100' }).errors.bank);
});

test('Excel TSV roundtrips quotes, newlines, tabs, leading zeros and empty trailing cells', () => {
    const rows = [['A\tB', 'Line 1\nLine 2', '"Quoted"', '001234', ''], ['Other', '', '', '', '']];
    assert.deepEqual(parseTsv(toTsv(rows)), rows);
    assert.deepEqual(parseTsv('ATK\t2\t1.000\r\nKertas\t3\t5.000\r\n'), [['ATK', '2', '1.000'], ['Kertas', '3', '5.000']]);
    assert.throws(() => parseTsv('"unclosed'));
    assert.throws(() => parseTsv('"closed"x'));
});

const honor = {
    no: '007',
    kode_jenis: '01', nominal: '1.000.000', pajak: '1', neto: '925.000',
    penerima: 'Penerima Test', nama: 'Rekening Test', bank: 'Bank Test',
    rekening: '00123456789012345678', identitas: '0012345678901234', npwp: '0012345678901234',
};
const honorOptions = { jenisPenghasilan: [{ value: '01', rate: '5.00' }, { value: '02', rate: '2.50' }] };

test('Honor tax is calculated from nominal and the selected income-type rate', () => {
    assert.deepEqual(calculateRow('honor', honor, honorOptions), {
        gross: 100000000, tax: 5000000, net: 95000000,
    });
    assert.equal(calculateRow('honor', { ...honor, pajak: '0', nominal: '2.000.000', npwp: '' }, honorOptions).tax, 10000000);
    assert.equal(calculateRow('honor', { ...honor, neto: '0' }, honorOptions).net, 95000000);
    assert.equal(calculateRow('honor', { ...honor, neto: '', nominal: '2.000.000' }, honorOptions).net, 190000000);
    assert.equal(schemas.honor.find((column) => column.key === 'neto').calculated, true);
    assert.equal(calculateRow('honor', {}).empty, true);
});

test('Honor rounds calculated tax to rupiah cents and rejects codes outside the master list', () => {
    assert.equal(calculateHonorTax('02', '100', honorOptions.jenisPenghasilan), '2,5');
    assert.equal(calculateHonorTax('01', '0,1', honorOptions.jenisPenghasilan), '0,01');
    assert.ok(calculateRow('honor', { ...honor, kode_jenis: 'OTHER' }, honorOptions).errors.kode_jenis);
    assert.ok(calculateRow('honor', { ...honor, kode_jenis: '', pajak: '' }, honorOptions).errors.kode_jenis);
});

test('Honor still validates recipient and identity', () => {
    assert.ok(calculateRow('honor', { ...honor, penerima: '' }, honorOptions).errors.penerima);
    assert.ok(calculateRow('honor', { ...honor, identitas: '' }, honorOptions).errors.identitas);
    assert.ok(calculateRow('honor', { ...honor, identitas: '12345678901234567' }, honorOptions).errors.identitas);
});

test('Short numeric identities are padded to 16 digits without changing passport values', () => {
    assert.equal(normalizeIdentity('123456'), '0000000000123456');
    assert.equal(normalizeIdentity('0012345678901234'), '0012345678901234');
    assert.equal(normalizeIdentity('AB123456'), 'AB123456');
    assert.equal(normalizeIdentity(''), '');
});

test('Honor clipboard columns preserve income codes and text identifiers', () => {
    const keys = schemas.honor.filter((column) => !column.computed).map((column) => column.key);
    assert.deepEqual(keys, ['kode_jenis', 'nominal', 'pajak', 'neto', 'penerima', 'nama', 'bank', 'rekening', 'identitas', 'npwp']);
    const values = keys.map((key) => honor[key]);
    assert.deepEqual(parseTsv(toTsv([values])), [values]);
});

test('Every clipboard schema excludes the optional No column without affecting totals', () => {
    for (const kind of Object.keys(schemas)) {
        assert.equal(calculateRow(kind, { pajak: '0' }).empty, true);
        assert.ok(!schemas[kind].some((column) => column.key === 'no'));
    }
});
