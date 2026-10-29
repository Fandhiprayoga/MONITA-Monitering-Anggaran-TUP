import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateRow, parseAmount, parseTsv, toTsv, schemas } from '../../public/js/monita/pencairan-grid.js';

test('Indonesian amounts are parsed exactly to cents; invalid formats are rejected', () => {
    assert.equal(parseAmount('1.250,50'), 125050);
    assert.equal(parseAmount('0'), 0);
    for (const value of ['', '-1', '1,250.50', '12.34', 'NaN', '1e3', '1,234', '10000000001']) {
        assert.throws(() => parseAmount(value));
    }
});

test('Impresfund calculates fractional volume and rounds to cents', () => {
    assert.equal(calculateRow('impresfund', { uraian: 'ATK', volume: '2,5', tarif: '1.250,50' }).gross, 312625);
    assert.ok(calculateRow('impresfund', { uraian: 'ATK', volume: '0', tarif: '100' }).errors.volume);
    assert.ok(calculateRow('impresfund', { uraian: 'ATK', volume: '10000000000', tarif: '10000000000' }).errors.tarif);
});

test('Beban uses gross and manual after-tax amount independently', () => {
    const values = { nominal: '100.000', pajak: '2.500', neto: '90.000', bank: 'Bank', nama: 'Nama', rekening: '00123456789012345678' };
    const result = calculateRow('beban', values);
    assert.deepEqual(result, { gross: 10000000, tax: 250000, net: 9000000 });
    assert.equal(calculateRow('beban', { ...values, nominal: '200.000', pajak: '5.000' }).net, 9000000);
    assert.equal(calculateRow('beban', { ...values, neto: '0' }).net, 0);
    for (const neto of ['', '-1', 'invalid']) {
        assert.ok(calculateRow('beban', { ...values, neto }).errors.neto);
    }
    assert.ok(calculateRow('beban', { nominal: '100', pajak: '101', bank: 'B', nama: 'N', rekening: '001' }).errors.pajak);
});

test('Blank rows are ignored, incomplete rows are invalid, zero tax is allowed', () => {
    assert.equal(calculateRow('beban', {}).empty, true);
    assert.ok(calculateRow('impresfund', { uraian: 'Incomplete' }).errors.volume);
    assert.equal(calculateRow('beban', { nominal: '100', pajak: '0', neto: '100', bank: 'B', nama: 'N', rekening: '001' }).tax, 0);
});

test('Beban clipboard includes editable after-tax amount in table order', () => {
    assert.deepEqual(schemas.beban.filter((column) => !column.computed).map((column) => column.key),
        ['nominal', 'pajak', 'neto', 'bank', 'nama', 'rekening']);
});

test('Tax defaults to zero without activating unused Honor and Beban rows', () => {
    for (const kind of ['honor', 'beban']) {
        assert.equal(schemas[kind].find((column) => column.key === 'pajak').defaultValue, '0');
        assert.deepEqual(calculateRow(kind, { pajak: '0' }), { empty: true, gross: 0, tax: 0, net: 0 });
        assert.ok(calculateRow(kind, { pajak: '1' }).errors.nominal);
        assert.ok(calculateRow(kind, { pajak: '0', nominal: '100' }).errors.bank);
    }
});

test('Excel TSV roundtrips quotes, newlines, tabs, leading zeros and empty trailing cells', () => {
    const rows = [['A\tB', 'Line 1\nLine 2', '"Quoted"', '001234', ''], ['Other', '', '', '', '']];
    assert.deepEqual(parseTsv(toTsv(rows)), rows);
    assert.deepEqual(parseTsv('ATK\t2\t1.000\r\nKertas\t3\t5.000\r\n'), [['ATK', '2', '1.000'], ['Kertas', '3', '5.000']]);
    assert.throws(() => parseTsv('"unclosed'));
    assert.throws(() => parseTsv('"closed"x'));
});

const honor = {
    kewarganegaraan: 'WNI', nominal: '1.000.000', pajak: '50.000', neto: '925.000',
    penerima: 'Penerima Test', nama: 'Rekening Test', bank: 'Bank Test',
    rekening: '00123456789012345678', identitas: '0012345678901234', npwp: '0012345678901234',
};

test('Honor uses manual received amount and accepts WNI/WNA including lowercase input', () => {
    for (const kewarganegaraan of ['WNI', 'WNA', 'wni', 'wna']) {
        assert.deepEqual(calculateRow('honor', { ...honor, kewarganegaraan }), {
            gross: 100000000, tax: 5000000, net: 92500000,
        });
    }
    assert.equal(calculateRow('honor', { ...honor, pajak: '0', nominal: '2.000.000', npwp: '' }).net, 92500000);
    assert.equal(calculateRow('honor', { ...honor, neto: '0' }).net, 0);
    assert.ok(calculateRow('honor', { ...honor, neto: '' }).errors.neto);
    assert.ok(calculateRow('honor', { ...honor, neto: '-1' }).errors.neto);
    assert.ok(calculateRow('honor', { ...honor, neto: 'invalid' }).errors.neto);
    assert.equal(calculateRow('honor', {}).empty, true);
});

test('Honor rejects invalid citizenship, missing recipient/identity and excessive or invalid tax', () => {
    assert.ok(calculateRow('honor', { ...honor, kewarganegaraan: 'OTHER' }).errors.kewarganegaraan);
    assert.ok(calculateRow('honor', { ...honor, penerima: '' }).errors.penerima);
    assert.ok(calculateRow('honor', { ...honor, identitas: '' }).errors.identitas);
    assert.ok(calculateRow('honor', { ...honor, pajak: '1.000.001' }).errors.pajak);
    assert.ok(calculateRow('honor', { ...honor, pajak: '-1' }).errors.pajak);
});

test('Honor clipboard columns include manual received amount and preserve text identifiers', () => {
    const keys = schemas.honor.filter((column) => !column.computed).map((column) => column.key);
    assert.deepEqual(keys, ['kewarganegaraan', 'nominal', 'pajak', 'neto', 'penerima', 'nama', 'bank', 'rekening', 'identitas', 'npwp']);
    const values = keys.map((key) => honor[key]);
    assert.deepEqual(parseTsv(toTsv([values])), [values]);
});
