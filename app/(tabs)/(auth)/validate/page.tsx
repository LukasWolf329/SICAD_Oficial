
import "../../../../style/global.css";
import axios from "axios";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { Link, useRouter } from "expo-router";
import React from 'react';
import { Image, Pressable, SafeAreaView, Text, TextInput, View } from 'react-native';
import NiceAlert from "../../../../components/NiceAlert/NiceAlert";
import { authCheck } from "@/app/authCheck/authCheck";
import { SafeAreaProvider } from "react-native-safe-area-context";

export default function Login() {

  //authCheck(); 

  const [codigo_certificado, setCodigo_certificado] = React.useState('');
  const [loading, setLoading] = React.useState(false);

  const [alertVisible, setAlertVisible] = React.useState(false);
  const [alertTitle, setAlertTitle] = React.useState('');
  const [alertMessage, setAlertMessage] = React.useState('');
  
  const router = useRouter();

  function openAlert(title: string, message: string) {
    setAlertTitle(title);
    setAlertMessage(message);
    setAlertVisible(true);
  }

  function formatarCodigo(value: string) {
    const raw = value
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, "") // remove tudo que não é letra/número
    .slice(0, 20);            // limita a 20 caracteres

    const partes = raw.match(/.{1,4}/g) ?? [];
    return partes.join("-");
  }

  function formatarDataBR(dateStr?: string) {
    if (!dateStr) return "";
    const iso = dateStr.includes(" ") ? dateStr.replace(" ", "T") : dateStr;
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString("pt-BR");
  }

  async function handleSignIn() {
        const codigoFormatado = formatarCodigo(codigo_certificado);

    if (!codigoFormatado || codigoFormatado.replace(/-/g, "").length !== 20) {
      openAlert(
        "Código inválido",
        "Digite um código no formato XXXX-XXXX-XXXX-XXXX-XXXX (20 caracteres)."
      );
      return;
    }

    setLoading(true);
    try {
      // Pode ser GET com params (mais simples)
      const { data } = await axios.get("http://192.168.1.9/SICAD/validar_certificado.php", {
        params: { codigo: codigoFormatado },
        timeout: 15000,
      });

      if (!data?.success) {
        openAlert("Erro ao verificar", data?.message ?? "Não foi possível verificar agora.");
        return;
      }

      if (!data.exists) {
        openAlert("Não encontrado", "Nenhum certificado foi encontrado com esse código.");
        return;
      }

      const c = data.certificado as {
        codigo: string;
        nome_usuario: string;
        nome_atividade: string;
        nome_palestrante?: string;
        data_emissao?: string;
      };

      openAlert(
        "Certificado válido ✅",
        `Código: ${c.codigo}\n\n` +
          `Nome: ${c.nome_usuario}\n` +
          `Atividade: ${c.nome_atividade}\n` +
          (c.nome_palestrante ? `Palestrante: ${c.nome_palestrante}\n` : "") +
          (c.data_emissao ? `Emissão: ${formatarDataBR(c.data_emissao)}` : "")
      );
    } catch (err: any) {
      const msg =
        err?.response?.data?.message ??
        err?.message ??
        "Falha de rede ao verificar o certificado.";
      openAlert("Erro de conexão", msg);
    } finally {
      setLoading(false);
    }
  }

  return (
    <View className="flex-1 flex-row bg-white dark:bg-[#121212]">
      <View id="aside" className=" w-5/12"> 
        <Image source={require('../../../../assets/images/side-view-login-cadastro.png')} style={{ width: '100%' }} className=" mobile:h-0 mobile:w-0 mobile:hidden"/>
      </View>
      <View className="flex-1 items-center justify-center">
        <View>
          <Image source={require('../../../../assets/images/logo-composta.png')} className="mb-4" />
          <Text className="text-6xl dark:color-white">Autenticidade de <br></br>Documento</Text>
          <form action="" className="flex flex-col gap-1 mt-4">
            <View>
              <SafeAreaProvider>
                <SafeAreaView>
                  <TextInput value={codigo_certificado} placeholder="Codigo" onChangeText={setCodigo_certificado} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4 color-slate-400"/>
                </SafeAreaView>
              </SafeAreaProvider>
            </View>
            <Pressable onPress={handleSignIn} className="w-full h-12 rounded-xl bg-green-600 text-2xl font-bold mt-4 color-white justify-center items-center">Verificar</Pressable>
          </form>
        </View>
      </View>
      <NiceAlert
      visible={alertVisible}
      title={alertTitle}
      message={alertMessage}
      onClose={() => setAlertVisible(false)}
    />
    </View>
  );
}
