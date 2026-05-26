import axios from "axios";
import React from "react";
import { Image, Pressable, SafeAreaView, Text, TextInput, View } from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";
import NiceAlert from "../../../../components/NiceAlert/NiceAlert";
import "../../../../style/global.css";

const API_URL = "https://sicad.linceonline.com.br/controller/validar_certificado.php";

type Certificado = {
  codigo: string;
  nome_usuario: string;
  nome_atividade: string;
  nome_palestrante?: string | null;
  data_emissao?: string | null;
};

type ApiResponse = {
  success?: boolean;
  exists?: boolean;
  message?: string;
  certificado?: Certificado;
};

function parseApiResponse(raw: unknown): ApiResponse | null {
  if (raw && typeof raw === "object") {
    return raw as ApiResponse;
  }

  if (typeof raw !== "string") {
    return null;
  }

  const texto = raw.trim();

  if (!texto) {
    return null;
  }

  try {
    return JSON.parse(texto) as ApiResponse;
  } catch {
    const inicioObjeto = texto.indexOf("{");
    const inicioArray = texto.indexOf("[");

    let inicioJson = -1;

    if (inicioObjeto >= 0 && inicioArray >= 0) {
      inicioJson = Math.min(inicioObjeto, inicioArray);
    } else {
      inicioJson = Math.max(inicioObjeto, inicioArray);
    }

    if (inicioJson < 0) {
      return null;
    }

    const candidato = texto.slice(inicioJson);

    try {
      return JSON.parse(candidato) as ApiResponse;
    } catch {
      const fimObjeto = candidato.lastIndexOf("}");

      if (fimObjeto >= 0) {
        try {
          return JSON.parse(candidato.slice(0, fimObjeto + 1)) as ApiResponse;
        } catch {
          return null;
        }
      }

      return null;
    }
  }
}

function normalizarCodigo(value: string) {
  return value.toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 24);
}

function formatarCodigo(value: string) {
  const raw = normalizarCodigo(value);
  const partes = raw.match(/.{1,4}/g) ?? [];
  return partes.join("-");
}

function formatarDataBR(dateStr?: string | null) {
  if (!dateStr) {
    return "";
  }

  const match = String(dateStr).match(/^(\d{4})-(\d{2})-(\d{2})/);

  if (match) {
    const [, ano, mes, dia] = match;
    return `${dia}/${mes}/${ano}`;
  }

  const iso = String(dateStr).includes(" ") ? String(dateStr).replace(" ", "T") : String(dateStr);
  const data = new Date(iso);

  if (Number.isNaN(data.getTime())) {
    return String(dateStr);
  }

  return data.toLocaleDateString("pt-BR");
}

export default function ValidarCertificado() {
  const [codigoCertificado, setCodigoCertificado] = React.useState("");
  const [loading, setLoading] = React.useState(false);

  const [alertVisible, setAlertVisible] = React.useState(false);
  const [alertTitle, setAlertTitle] = React.useState("");
  const [alertMessage, setAlertMessage] = React.useState("");

  function openAlert(title: string, message: string) {
    setAlertTitle(title);
    setAlertMessage(message);
    setAlertVisible(true);
  }

  async function handleVerifyCertificate() {
    const codigoFormatado = formatarCodigo(codigoCertificado);
    const codigoRaw = normalizarCodigo(codigoFormatado);

    if (codigoRaw.length !== 24) {
      openAlert(
        "Código inválido",
        "Digite um código no formato XXXX-XXXX-XXXX-XXXX-XXXX-XXXX (24 caracteres)."
      );
      return;
    }

    setLoading(true);

    try {
      const response = await axios.get(API_URL, {
        params: { codigo: codigoFormatado },
        timeout: 15000,
        validateStatus: () => true,
        transformResponse: [(data) => data],
      });

      const data = parseApiResponse(response.data);

      if (!data || typeof data !== "object") {
        openAlert("Erro ao verificar", "Resposta inválida do servidor.");
        return;
      }

      if (!data.success) {
        openAlert("Erro ao verificar", data.message ?? "Não foi possível verificar agora.");
        return;
      }

      if (!data.exists || !data.certificado) {
        openAlert("Não encontrado", "Nenhum certificado foi encontrado com esse código.");
        return;
      }

      const c = data.certificado;

      openAlert(
        "Certificado válido ✅",
        `Código: ${c.codigo}\n\n` +
          `Nome: ${c.nome_usuario}\n` +
          `Atividade: ${c.nome_atividade}\n` +
          (c.nome_palestrante ? `Palestrante: ${c.nome_palestrante}\n` : "") +
          (c.data_emissao ? `Emissão: ${formatarDataBR(c.data_emissao)}` : "")
      );
    } catch (error: unknown) {
      if (axios.isAxiosError(error)) {
        const data = parseApiResponse(error.response?.data);
        const message =
          data?.message ??
          error.message ??
          "Falha de rede ao verificar o certificado.";

        openAlert("Erro de conexão", message);
        return;
      }

      openAlert("Erro de conexão", "Falha de rede ao verificar o certificado.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaProvider>
      <View className="flex-1 flex-row bg-white dark:bg-[#121212]">
        <View id="aside" className="w-5/12">
          <Image
            source={require("../../../../assets/images/side-view-login-cadastro.png")}
            style={{ width: "100%" }}
            className="mobile:h-0 mobile:w-0 mobile:hidden"
          />
        </View>

        <View className="flex-1 items-center justify-center">
          <View>
            <Image source={require("../../../../assets/images/logo-composta.png")} className="mb-4" />

            <Text className="text-6xl dark:color-white">
              Autenticidade de{"\n"}Documento
            </Text>

            <View className="flex flex-col gap-1 mt-4">
              <View>
                <Text className="text-2xl dark:color-white">Código do certificado</Text>

                <SafeAreaView>
                  <TextInput
                    value={codigoCertificado}
                    placeholder="Código"
                    onChangeText={(value) => setCodigoCertificado(formatarCodigo(value))}
                    autoCapitalize="characters"
                    autoCorrect={false}
                    maxLength={29}
                    returnKeyType="go"
                    onSubmitEditing={handleVerifyCertificate}
                    className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4 color-slate-400"
                  />
                </SafeAreaView>
              </View>

              <Pressable
                onPress={handleVerifyCertificate}
                disabled={loading}
                className={`w-full h-12 rounded-xl bg-green-600 mt-4 justify-center items-center ${
                  loading ? "opacity-60" : ""
                }`}
              >
                <Text className="text-2xl font-bold text-white">
                  {loading ? "Verificando..." : "Verificar"}
                </Text>
              </Pressable>
            </View>
          </View>
        </View>

        <NiceAlert
          visible={alertVisible}
          title={alertTitle}
          message={alertMessage}
          onClose={() => setAlertVisible(false)}
        />
      </View>
    </SafeAreaProvider>
  );
}